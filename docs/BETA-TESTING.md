# AoE2 Companion — Guía de Instalación (Beta)

Hola, gracias por probar la beta. Esta guía te lleva de cero a tu primera partida ranked en ~5 minutos. Si te falla algo, mensaje al canal de Discord.

---

## 1. Instalar el companion

1. Descargá `AoE2CompanionSetup-X.Y.Z.exe` (link te lo paso por Discord/email).
2. Doble-click al archivo para correrlo.

### ⚠ Aparece una pantalla naranja "Microsoft Defender SmartScreen"

Es esperable. Significa que Windows no reconoce el publicador de la aplicación (todavía no tenemos certificado de firma de código — eso cuesta plata y lo pagaremos cuando salgamos de beta).

**Para continuar:**
- Click en **"Más información"** (texto chico, abajo del título naranja)
- Click en **"Ejecutar de todos modos"** que aparece al final

Después de eso, el instalador funciona normal. Siguiente, siguiente, instalar.

> Si tu Windows está super-locked-down y ni siquiera te aparece "Ejecutar de todos modos", hacé click derecho sobre el archivo `setup.exe` → Propiedades → tildá "Desbloquear" abajo → OK. Volvé a doble-clickar y va a aparecer la opción.

### Dónde queda instalado

`%LOCALAPPDATA%\Programs\AoE2Companion\` (no requiere admin). Aparece en el menú Inicio como "AoE2 Companion" y en "Agregar/quitar programas" para desinstalación limpia.

---

## 2. Setup inicial (primera vez)

Cuando arranques el companion por primera vez, te va a pedir un **token de API** para conectarse al backend.

1. Abrí en el navegador: **(URL del backend, te la paso aparte)**
2. Iniciá sesión con Steam.
3. En el dashboard, sección "Companion", clickeá **"Generar nuevo token"**.
4. Copiá el token largo que aparece (algo tipo `1|abc123...`). Solo se muestra una vez — guardalo.
5. Volvé al companion, pegalo en el campo "Token", click en "Guardar".

Listo. El companion queda corriendo en background mostrando logs en una ventana de consola. **No la cierres** mientras quieras jugar — es lo que automatiza la creación del lobby cuando se te asigna una partida.

---

## 3. Tu primera partida

1. En la web, click en **"Buscar partida"**.
2. Si hay otro jugador en cola te empareja al toque. Si no, esperás un poco. (Para testing tenés un Bot Dev permanente en cola, así no te quedás trabado nunca).
3. Te llevan al **draft de mapas**: 10 mapas, banean alternado uno cada uno. Tenés 30s por turno (si te pasás, banea el sistema).
4. Después al **draft de civilizaciones**: 3 fases (cada uno picks 4, ban 2 del rival, final pick 1). 60s por fase.
5. Una vez completado, el companion del host arma el lobby en AoE2 automáticamente — no toques nada en ese momento, dejá que escriba solo. **Mover el mouse o teclear durante esos ~10s puede romperlo**.
6. El joiner ve el link, AoE2 abre el lobby automáticamente.
7. Click en **"Iniciar partida"** dentro de AoE2 (uno de los dos, normalmente el host). Jugá.
8. Cuando termina la partida, el companion sube el replay automáticamente y aplica la actualización de rating.

---

## 4. Cosas que pueden salir mal (las conocidas)

- **El companion se queda colgado configurando el lobby**: si AoE2 te tomó el foco mientras configuraba, puede confundirse. Cerrá el companion, el lobby de AoE2, y volvé a entrar a la cola. Avisame con un screenshot del log.
- **"Permitir espectadores" desmarcado**: en versiones recientes el companion lo detecta y lo activa. Si ves que el lobby quedó con espectadores desmarcado igual, escribime.
- **Te marcaste WIN/LOSS pero el rating no actualizó**: por ahora hay un bug conocido — el parser de replays está atrasado vs el último patch del juego, así que el match queda como "completed" pero sin rating actualizado. Va a resolverse solo cuando el parser se actualice (no es problema de tu lado).
- **Quedás "in_progress" para siempre después de una partida**: el companion del rival probablemente se cerró antes de que terminara. Después de 5min se marca como walkover automáticamente y vos ganás.

---

## 5. Cómo reportar bugs / dar feedback

1. **Logs del companion**: la ventana de consola tiene todo lo que hizo. Si algo falla, copiá las últimas ~50 líneas con Ctrl+A → Enter (selecciona todo + copia en esa ventana).
2. Mandame al canal de Discord:
   - Qué intentaste hacer
   - Qué esperabas
   - Qué pasó
   - El log si lo tenés
   - Screenshot si aplica

No hay un sistema de bug tracking todavía — todo va por Discord directo conmigo.

---

## 6. Desinstalar

Panel de Control → Programas → "AoE2 Companion" → Desinstalar. Limpio.

> Tu token + URL del backend NO se borran al desinstalar (quedan en `%APPDATA%\AoE2Companion\config.json`). Si querés limpiar todo, borrá esa carpeta a mano.

---

## Versiones

| Versión | Fecha | Notas |
|---------|-------|-------|
| 0.1.0   | 2026-05-06 | Primera release de beta. Companion + queue + drafts + Glicko-2 + replay upload + forfeit detection + server selection por ping. |
