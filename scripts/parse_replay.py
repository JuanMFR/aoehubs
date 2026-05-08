#!/usr/bin/env python3
"""
parse_replay.py - extrae metadata de un .aoe2record usando mgz-fast.

mgz-fast es el fork de mgz que mantiene aoe2insights, soporta el patch actual
de DE (a diferencia del happyleavesaoc/aoc-mgz upstream que esta atrasado).

Uso:
    python parse_replay.py <path_al_replay>

Salida (siempre JSON, siempre exit 0 salvo error fatal):
    {"ok": true, "data": {...}}        # parsing OK
    {"ok": false, "error": "...",      # parsing fallo
     "type": "parse_error"}

Lo invoca app/Services/ReplayParser.php via Symfony Process.
"""

import json
import os
import sys


def _load_civ_names():
    """Mapea civilization_id -> nombre. Usa el dataset 100 de aocref que es
    el de DE actual (incluye Georgians, Romans, Armenians, etc.)."""
    try:
        import aocref
        ds_path = os.path.join(os.path.dirname(aocref.__file__), 'data', 'datasets', '100.json')
        with open(ds_path) as f:
            ds = json.load(f)
        # civilizations es dict {"id": {...}} — armamos {int_id: name}
        return {int(k): v.get('name') for k, v in ds.get('civilizations', {}).items()}
    except Exception:
        return {}


def _load_map_names():
    """Mapea rms_map_id -> nombre estandar del mapa (Arabia, Nomad, etc).
    Usar DE_MAP_NAMES en lugar de `rms_filename` porque ese ultimo lleva el
    nombre de archivo RMS literal y se ensucia con map packs del Steam
    Workshop (ej: el user con LP map pack ve 'LP Arena.rms' aunque eligio
    Nomad — el rms_map_id sigue siendo 33 = Nomad correctamente)."""
    try:
        from mgz.const import DE_MAP_NAMES
        return DE_MAP_NAMES
    except Exception:
        return {}


def _decode(x):
    """Decode bytes to str recursivamente. mgz-fast devuelve nombres como
    bytes; necesitamos str para serializar JSON."""
    if isinstance(x, bytes):
        return x.decode('utf-8', errors='replace')
    if isinstance(x, dict):
        return {k: _decode(v) for k, v in x.items()}
    if isinstance(x, list):
        return [_decode(v) for v in x]
    return x


def parse(replay_path: str) -> dict:
    try:
        from mgz.fast.header import parse as parse_header
        from mgz.fast import operation, meta
        from mgz.fast.enums import Operation, Action
    except ImportError as e:
        return {"ok": False, "type": "mgz_missing", "error": f"mgz-fast no instalado: {e}"}

    civ_names = _load_civ_names()
    map_names = _load_map_names()

    try:
        with open(replay_path, "rb") as f:
            eof = os.fstat(f.fileno()).st_size
            header = parse_header(f)

            # meta() lee la seccion entre header y body (frame seed, etc).
            # Hace falta llamarla antes de iterar el body.
            try:
                meta(f)
            except Exception:
                pass  # opcional, algunos replays no la tienen

            de = header.get("de") or {}

            # Players: combinamos info del header con de.players (8 slots).
            # Clasificamos por señales del campo en lugar de magic numbers de
            # `type` (varían entre versiones de DE):
            #   - human = profile_id real (≠ 0xFFFFFFFF) + name no vacío
            #   - ai    = ai_name no vacío (AoE2 le pone nombre tipo "Miguel...")
            #   - resto = open/closed slot (ignorar)
            humans = []
            ais    = []
            INVALID_PROFILE_ID = 0xFFFFFFFF  # 4294967295 = slot sin player real
            for p in (de.get("players") or []):
                name    = _decode(p.get("name"))
                ai_name = _decode(p.get("ai_name"))
                pid     = p.get("profile_id")

                entry = {
                    "name":            name,
                    "ai_name":         ai_name,
                    "profile_id":      pid,
                    "civilization_id": p.get("civilization_id"),
                    "civilization":    civ_names.get(p.get("civilization_id")),
                    "color_id":        p.get("color_id"),
                    "team_id":         p.get("team_id"),
                    "number":          p.get("number"),
                    "type":            p.get("type"),
                }
                if name and pid is not None and pid != INVALID_PROFILE_ID:
                    humans.append(entry)
                elif ai_name:
                    ais.append(entry)

            # Body iteration. Tracking:
            #   - saw_postgame: si vimos un Operation.POSTGAME al final
            #     (= AoE2 escribió el block de postgame = game terminó natural)
            #   - resigned_players: lista de player_num (slot) que se rindieron
            #   - postgame_data: contenido del postgame (leaderboards con rank)
            #
            # `completed` = saw_postgame. Si el body termina sin postgame, fue
            # truncado o abortado (alt+f4, crash, salir-al-menu desde pausa, etc).
            saw_postgame      = False
            postgame_data     = None
            resigned_players  = []
            ops_count         = 0
            iter_error        = None
            chat              = []
            try:
                while f.tell() < eof:
                    op_type, payload = operation(f)
                    ops_count += 1

                    if op_type == Operation.ACTION:
                        # `payload` para ACTION es (Action_enum, action_payload_dict)
                        if isinstance(payload, tuple) and len(payload) == 2:
                            action_type, action_payload = payload
                            if action_type == Action.RESIGN and isinstance(action_payload, dict):
                                pid = action_payload.get('player_id')
                                if pid is not None: resigned_players.append(pid)
                    elif op_type == Operation.POSTGAME:
                        saw_postgame = True
                        if isinstance(payload, dict): postgame_data = payload
                    elif op_type == Operation.CHAT and payload:
                        try: chat.append(payload.decode("utf-8", errors="replace"))
                        except Exception: pass
            except Exception as e:
                iter_error = f"{type(e).__name__}: {str(e)[:120]}"

            # Determinar winner desde postgame.leaderboards (rank 1 = ganador)
            winner_player_num = None
            if postgame_data and 'leaderboards' in postgame_data:
                for lb in postgame_data['leaderboards']:
                    for p in lb.get('players', []):
                        if p.get('rank') == 1:
                            winner_player_num = p.get('player_num')
                            break
                    if winner_player_num is not None: break

            # Fallback: exactamente 1 resign → el primer player vivo (humano o AI)
            # con número de slot >0 gana. Cubre 1v1 humano-vs-humano (otro
            # humano gana) y 1v1 humano-vs-bot (la AI "gana" si el humano se
            # rindió).
            if winner_player_num is None and len(resigned_players) == 1:
                resigned = resigned_players[0]
                candidates = humans + ais
                survivors = sorted(
                    [p for p in candidates
                     if p.get("number") is not None
                     and p.get("number") > 0
                     and p.get("number") != resigned],
                    key=lambda p: p["number"]
                )
                if survivors:
                    winner_player_num = survivors[0]["number"]

            data = {
                # General
                "version":         str(header.get("version") or ""),
                "game_version":    _decode(header.get("game_version")),
                "save_version":    header.get("save_version"),
                "completed":          saw_postgame,
                "saw_postgame":       saw_postgame,
                "iter_error":         iter_error,
                "ops_count":          ops_count,
                "resigned_players":   resigned_players,
                "winner_player_num":  winner_player_num,

                # Identidad / players
                "humans":          humans,
                "ais":             ais,
                "humans_count":    len(humans),

                # Settings / lobby
                "lobby_name":      _decode(de.get("lobby")),
                "rms_filename":    _decode(de.get("rms_filename")),  # archivo RMS literal — solo para debug
                "rms_map_id":      de.get("rms_map_id"),
                "map_name":        map_names.get(de.get("rms_map_id")),  # nombre estandar — usar para validacion
                "settings": {
                    "population_limit":  de.get("population_limit"),
                    "lock_teams":        de.get("lock_teams"),
                    "lock_speed":        de.get("lock_speed"),
                    "cheats":            de.get("cheats"),
                    "treaty_length":     de.get("treaty_length"),
                    "shared_exploration":de.get("shared_exploration"),
                    "team_together":     de.get("team_together"),
                    "team_positions":    de.get("team_positions"),
                    "starting_age_id":   de.get("starting_age_id"),
                    "ending_age_id":     de.get("ending_age_id"),
                    "starting_resources_id": de.get("starting_resources_id"),
                    "victory_type_id":   de.get("victory_type_id"),
                    "visibility_id":     de.get("visibility_id"),
                    "all_technologies":  de.get("all_technologies"),
                    "turbo_enabled":     de.get("turbo_enabled"),
                    "speed":             de.get("speed"),
                    "multiplayer":       de.get("multiplayer"),
                    "rated":             de.get("rated"),
                },
                "mod":             _decode(de.get("mod")),  # vacio = vanilla
                "build":           de.get("build"),
                "timestamp":       de.get("timestamp"),

                # Chat (util para detectar coordinacion / coincidencias)
                "chat_count":      len(chat),
            }

            return {"ok": True, "data": data}

    except FileNotFoundError:
        return {"ok": False, "type": "file_not_found", "error": f"replay no encontrado: {replay_path}"}
    except Exception as e:
        return {
            "ok":    False,
            "type":  "parse_error",
            "error": f"{type(e).__name__}: {str(e)[:500]}",
        }


def main() -> int:
    if len(sys.argv) != 2:
        print(json.dumps({"ok": False, "type": "bad_args", "error": "uso: parse_replay.py <path>"}))
        return 0
    result = parse(sys.argv[1])
    # ensure_ascii=True para que el output sea siempre ASCII puro. Sin esto,
    # Python en stdout de subprocess Windows puede usar cp1252 y romper el
    # json_decode del lado PHP cuando hay caracteres non-ASCII (acentos en
    # nombres de jugador, chat con tildes, etc).
    print(json.dumps(result, default=str, ensure_ascii=True))
    return 0


if __name__ == "__main__":
    sys.exit(main())
