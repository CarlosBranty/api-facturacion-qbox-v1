# 🔑 Sistema de Tokens y Autenticación - Resumen Rápido

## ✅ ¿Necesito un token para usar el API?

**Sí**, pero puedes usar **cualquiera de estos dos tipos**:

### 1. Token de Usuario (Sanctum)
- **Obtener**: `POST /api/auth/login` con email y password
- **Usar**: `Authorization: Bearer {token_usuario}`
- **Para**: Gestión administrativa del sistema
- **Acceso**: Todas las rutas `/api/v1/*`

### 2. Token de Empresa
- **Obtener**: Se crea automáticamente al crear una empresa, o crear uno con `POST /api/v1/companies/{id}/tokens`
- **Usar**: `Authorization: Bearer {token_empresa}` o `X-API-Key: {token_empresa}`
- **Para**: Integraciones desde otras aplicaciones
- **Acceso**: Todas las rutas `/api/v1/*` (mismas que token de usuario)

## 🎯 Funcionamiento

El sistema acepta **ambos tipos de tokens** en las mismas rutas. El middleware intenta primero con token de usuario, y si no funciona, intenta con token de empresa.

## 📝 Ejemplos

### Con Token de Usuario (como antes)
```bash
# 1. Login
curl -X POST "http://localhost:8000/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"email": "admin@example.com", "password": "password"}'

# 2. Usar el token
curl -X GET "http://localhost:8000/api/v1/invoices" \
  -H "Authorization: Bearer {token_usuario}"
```

### Con Token de Empresa (nuevo)
```bash
# 1. Obtener token de empresa (se crea automáticamente al crear empresa)
curl -X GET "http://localhost:8000/api/v1/companies/1/tokens" \
  -H "Authorization: Bearer {token_usuario}"

# 2. Usar el token de empresa directamente
curl -X GET "http://localhost:8000/api/v1/invoices" \
  -H "Authorization: Bearer {token_empresa}"

# O crear una factura sin especificar company_id (se usa automáticamente)
curl -X POST "http://localhost:8000/api/v1/invoices" \
  -H "Authorization: Bearer {token_empresa}" \
  -H "Content-Type: application/json" \
  -d '{
    "branch_id": 1,
    "client": {...},
    "serie": "F001",
    ...
  }'
```

## 🔒 Seguridad

- Los tokens de empresa solo pueden acceder a los datos de su propia empresa
- Si proporcionas `company_id` en una solicitud, se valida que coincida con la empresa del token
- Los super administradores (con token de usuario) pueden acceder a todas las empresas

## 📚 Más Información

Ver `TOKENS_Y_SUSCRIPCIONES.md` para documentación completa.

