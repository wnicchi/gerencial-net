# Recuperación de accesos / contraseñas — Sistema RRHH

Guía rápida de cómo se recuperan las contraseñas en el sistema, según el caso.
El login **no** depende del campo `ESTADO` (que en FoxPro es el flag de sesión):
un usuario con contraseña siempre puede iniciar sesión.

---

## 1. Un usuario común olvidó su contraseña

Tiene dos caminos:

- **Auto-recuperación (si tiene email registrado):** en la pantalla de login,
  clic en **"¿Olvidaste tu contraseña?"** → ingresa su usuario → le llega un
  **código de 6 dígitos a su email** → lo ingresa → define una nueva contraseña.
  (El código va siempre al email ya registrado; no se puede cambiar el email en
  ese paso, por seguridad.)

- **Si NO tiene email registrado:** la propia pantalla le indica que debe
  **pedirle la contraseña al administrador** (ver punto 2).

---

## 2. El administrador le asigna la contraseña a un usuario

Desde el sistema, con un usuario administrador:

1. **Menú → Sistema → Seguridad → Administración de Usuarios.**
2. Elegir el usuario de la lista.
3. Botón **🔒 Establecer contraseña**.
4. Escribir una clave o tocar **🎲 Generar una automática** (ej. `Rrhh4827!`).
5. **Guardar** y comunicarle la clave al usuario.

El usuario queda listo para entrar de inmediato (no depende de email).

---

## 3. RESCATE: el administrador olvidó SU propia contraseña

Si el administrador no puede entrar y **no tiene email** para auto-recuperarse
ni hay otro administrador que le asigne la clave, se resuelve desde el **servidor**
(quien tenga acceso por Escritorio Remoto / RDP).

**En el servidor, PowerShell:**

```powershell
cd C:\inetpub\rrhh-net\backend
php artisan usuario:password WNICCHI "NuevaClave123!"
```

- Reemplazar `WNICCHI` por el **login** del usuario a recuperar.
- Reemplazar `"NuevaClave123!"` por la nueva contraseña (mínimo 6 caracteres; las
  comillas son necesarias si tiene símbolos o espacios).
- Sirve para **cualquier** usuario, incluido un administrador.
- Deja la cuenta activa y cierra las sesiones anteriores.

Salida esperada:

```
Contraseña de WALTER NICCHI (WNICCHI) actualizada. Ya puede iniciar sesión.
```

---

## Recomendaciones para no quedar nunca afuera

- Cargarle **email a los usuarios administradores** (así pueden auto-recuperarse
  por código sin depender de nadie).
- Mantener **al menos dos administradores**, para que uno pueda asignarle la
  clave al otro desde la pantalla de Administración de Usuarios.
- El comando `usuario:password` es la red de seguridad final: siempre funciona
  desde el servidor, sin email ni segundo administrador.
