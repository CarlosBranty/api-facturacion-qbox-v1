# Sistema de Tokens y Suscripciones por Empresa

Este documento explica cómo funciona el sistema de tokens de API y suscripciones implementado en el proyecto.

## 📋 Tabla de Contenidos

1. [Sistema de Tokens por Empresa](#sistema-de-tokens-por-empresa)
2. [Sistema de Suscripciones](#sistema-de-suscripciones)
3. [Uso del API con Tokens de Empresa](#uso-del-api-con-tokens-de-empresa)
4. [Gestión de Tokens](#gestión-de-tokens)
5. [Gestión de Suscripciones](#gestión-de-suscripciones)
6. [Ejemplos de Uso](#ejemplos-de-uso)

## 🔑 Sistema de Tokens por Empresa

### ¿Qué son los tokens de empresa?

Los tokens de empresa son credenciales de acceso que permiten a una empresa consumir el API directamente desde otra aplicación, sin necesidad de autenticarse con un usuario. Cada empresa puede tener múltiples tokens con diferentes permisos y restricciones.

### ⚡ Creación Automática de Tokens

**IMPORTANTE**: Cuando se crea una nueva empresa, el sistema **automáticamente crea un token de API por defecto** con:
- Nombre: "Token Principal - {Razón Social}"
- Permisos: Todos los permisos (`*`)
- Expiración: Sin expiración
- Estado: Activo

Este token se puede usar inmediatamente después de crear la empresa. Puedes crear tokens adicionales con permisos más restrictivos según tus necesidades.

### Características

- **Tokens únicos**: Cada token es único y se almacena con hash SHA-256
- **Permisos granulares**: Cada token puede tener permisos específicos (abilities)
- **Restricciones de IP**: Opcionalmente, puedes restringir el uso del token a IPs específicas
- **Rate limiting**: Límites de solicitudes por día y por minuto
- **Expiración**: Los tokens pueden tener fecha de expiración
- **Tracking de uso**: Se registra la última vez que se usó el token y desde qué IP

## 💳 Sistema de Suscripciones

### ¿Qué son las suscripciones?

Las suscripciones permiten controlar el acceso de las empresas al API basándose en planes de pago. Puedes definir diferentes planes con límites y características específicas.

### Estados de Suscripción

- **active**: Suscripción activa y válida
- **inactive**: Suscripción inactiva
- **expired**: Suscripción expirada
- **cancelled**: Suscripción cancelada
- **suspended**: Suscripción suspendida

### Tipos de Plan

- **monthly**: Plan mensual
- **yearly**: Plan anual
- **lifetime**: Plan de por vida

### Límites Configurables

- `max_documents_per_month`: Límite de documentos por mes (null = ilimitado)
- `max_total_documents`: Límite total de documentos desde el inicio de la suscripción (null = ilimitado)
- `max_total_sales_amount`: Límite total de ventas en monto desde el inicio de la suscripción (null = ilimitado)
- `max_users`: Número máximo de usuarios
- `max_branches`: Número máximo de sucursales
- `features`: Array de características adicionales del plan

**Nota**: Los límites se verifican automáticamente antes de crear cada documento (facturas, boletas, etc.). Si se alcanza un límite, la creación del documento será rechazada con un mensaje de error detallado.

## 🚀 Uso del API con Tokens de Empresa

### ⚡ Autenticación Dual

**IMPORTANTE**: El sistema ahora acepta **ambos tipos de tokens** en todas las rutas `/api/v1/*`:

1. **Token de Usuario (Sanctum)**: Para gestión administrativa
2. **Token de Empresa**: Para integraciones desde otras aplicaciones

El middleware `AuthenticateApiToken` intenta primero autenticar con token de usuario, y si falla, intenta con token de empresa. Esto significa que **puedes usar cualquiera de los dos** en las mismas rutas.

### Autenticación

Para usar el API con un token de empresa, debes incluir el token en el header de la solicitud:

```bash
# Opción 1: Header Authorization (recomendado)
Authorization: Bearer {tu_token_aqui}

# Opción 2: Header X-API-Key
X-API-Key: {tu_token_aqui}
```

### Ejemplo con cURL

```bash
curl -X GET "https://api.ejemplo.com/api/v1/company/info" \
  -H "Authorization: Bearer tu_token_aqui"
```

### Ejemplo con JavaScript (fetch)

```javascript
const response = await fetch('https://api.ejemplo.com/api/v1/company/info', {
  headers: {
    'Authorization': 'Bearer tu_token_aqui',
    'Content-Type': 'application/json'
  }
});

const data = await response.json();
console.log(data);
```

### Ejemplo con PHP (Guzzle)

```php
use GuzzleHttp\Client;

$client = new Client([
    'base_uri' => 'https://api.ejemplo.com',
    'headers' => [
        'Authorization' => 'Bearer tu_token_aqui',
        'Content-Type' => 'application/json',
    ]
]);

// Puedes usar el token de empresa en CUALQUIER ruta /api/v1/*
$response = $client->get('/api/v1/invoices'); // Funciona con token de empresa
$data = json_decode($response->getBody(), true);
```

### 🔄 Compatibilidad con Tokens de Usuario

**Todas las rutas principales funcionan con ambos tipos de tokens:**

- ✅ `/api/v1/invoices` - Funciona con token de usuario O token de empresa
- ✅ `/api/v1/boletas` - Funciona con token de usuario O token de empresa
- ✅ `/api/v1/clients` - Funciona con token de usuario O token de empresa
- ✅ `/api/v1/branches` - Funciona con token de usuario O token de empresa
- ✅ Y todas las demás rutas...

**Cuando usas un token de empresa:**
- Si no proporcionas `company_id`, se usa automáticamente la empresa del token
- Si proporcionas `company_id`, se valida que sea la misma empresa del token
- Los super administradores (con token de usuario) pueden acceder a todas las empresas

## 🔧 Gestión de Tokens

### Crear un Token (Requiere autenticación de usuario)

**Endpoint**: `POST /api/v1/companies/{company_id}/tokens`

**Headers requeridos**:
```
Authorization: Bearer {token_usuario}
Content-Type: application/json
```

**Body**:
```json
{
  "name": "Token para integración ERP",
  "abilities": ["invoices.create", "invoices.view", "boletas.create"],
  "expires_at": "2025-12-31 23:59:59",
  "allowed_ips": ["192.168.1.100", "10.0.0.0/8"],
  "max_requests_per_day": 10000,
  "max_requests_per_minute": 100
}
```

**Respuesta**:
```json
{
  "message": "Token creado exitosamente",
  "token": {
    "id": 1,
    "name": "Token para integración ERP",
    "token": "abc123...xyz789",
    "abilities": ["invoices.create", "invoices.view", "boletas.create"],
    "expires_at": "2025-12-31 23:59:59",
    "created_at": "2025-01-15 10:00:00"
  },
  "warning": "Guarda este token de forma segura. No se mostrará nuevamente."
}
```

### Listar Tokens

**Endpoint**: `GET /api/v1/companies/{company_id}/tokens`

### Ver un Token Específico

**Endpoint**: `GET /api/v1/companies/{company_id}/tokens/{token_id}`

**Nota**: El token completo solo se muestra al crearlo. Después solo se muestran metadatos.

### Actualizar un Token

**Endpoint**: `PUT /api/v1/companies/{company_id}/tokens/{token_id}`

**Body**:
```json
{
  "name": "Token actualizado",
  "is_active": true,
  "abilities": ["*"],
  "expires_at": "2026-12-31 23:59:59"
}
```

### Revocar un Token

**Endpoint**: `DELETE /api/v1/companies/{company_id}/tokens/{token_id}`

### Regenerar un Token

**Endpoint**: `POST /api/v1/companies/{company_id}/tokens/{token_id}/regenerate`

Crea un nuevo token y revoca el anterior.

## 📊 Gestión de Suscripciones

### Crear una Suscripción (Solo Super Admin)

**Endpoint**: `POST /api/v1/companies/{company_id}/subscriptions`

**Body**:
```json
{
  "plan_name": "premium",
  "plan_type": "monthly",
  "price": 299.00,
  "currency": "PEN",
  "starts_at": "2025-01-15 00:00:00",
  "ends_at": "2025-02-15 23:59:59",
  "max_documents_per_month": 10000,
  "max_total_documents": 50000,
  "max_total_sales_amount": 1000000.00,
  "max_users": 5,
  "max_branches": 3,
  "features": ["api_access", "priority_support", "custom_integrations"],
  "payment_method": "stripe",
  "payment_reference": "ch_1234567890"
}
```

**Campos de límites**:
- `max_documents_per_month`: Límite mensual (null = ilimitado)
- `max_total_documents`: Límite total desde el inicio (null = ilimitado)
- `max_total_sales_amount`: Límite total de monto de ventas (null = ilimitado)

### Listar Suscripciones

**Endpoint**: `GET /api/v1/companies/{company_id}/subscriptions`

### Ver Suscripción Activa

**Endpoint**: `GET /api/v1/companies/{company_id}/subscriptions/active`

### Activar Suscripción

**Endpoint**: `POST /api/v1/companies/{company_id}/subscriptions/{subscription_id}/activate`

### Cancelar Suscripción

**Endpoint**: `POST /api/v1/companies/{company_id}/subscriptions/{subscription_id}/cancel`

### Renovar Suscripción

**Endpoint**: `POST /api/v1/companies/{company_id}/subscriptions/{subscription_id}/renew`

**Body**:
```json
{
  "months": 1
}
```

## 📊 Verificación Automática de Límites

El sistema verifica automáticamente los límites de suscripción antes de crear cualquier documento (facturas, boletas, etc.). Si se alcanza un límite, recibirás un error con información detallada:

```json
{
  "success": false,
  "message": "Límite de suscripción alcanzado. Documentos restantes: 5. Monto restante: 1,500.00 PEN.",
  "status": "error",
  "subscription_limits": {
    "max_total_documents": 1000,
    "total_documents_created": 995,
    "remaining_documents": 5,
    "max_total_sales_amount": 50000.00,
    "total_sales_amount": 48500.00,
    "remaining_sales_amount": 1500.00
  }
}
```

### Contadores Automáticos

Los contadores se actualizan automáticamente cuando se crea un documento:
- `total_documents_created`: Se incrementa en 1 por cada documento creado
- `total_sales_amount`: Se incrementa con el monto total del documento (`mto_imp_venta`)

## 📝 Ejemplos de Uso

### Ejemplo 1: Crear un token para integración

```bash
# 1. Autenticarse como usuario
curl -X POST "https://api.ejemplo.com/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@empresa.com",
    "password": "password123"
  }'

# Respuesta incluye access_token del usuario

# 2. Crear token de empresa
curl -X POST "https://api.ejemplo.com/api/v1/companies/1/tokens" \
  -H "Authorization: Bearer {access_token_usuario}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Token ERP",
    "abilities": ["*"],
    "expires_at": "2025-12-31 23:59:59"
  }'

# Guardar el token retornado
```

### Ejemplo 2: Usar el token para crear una factura

```bash
curl -X POST "https://api.ejemplo.com/api/v1/invoices" \
  -H "Authorization: Bearer {token_empresa}" \
  -H "Content-Type: application/json" \
  -d '{
    "serie": "F001",
    "numero": 1,
    "fecha_emision": "2025-01-15",
    ...
  }'
```

### Ejemplo 3: Verificar suscripción activa

```bash
curl -X GET "https://api.ejemplo.com/api/v1/companies/1/subscriptions/active" \
  -H "Authorization: Bearer {token_usuario}"
```

## ⚙️ Configuración

### Habilitar/Deshabilitar Requisito de Suscripción

En el archivo `.env` o `config/app.php`:

```php
// Si es true, las empresas necesitan suscripción activa para usar el API
REQUIRE_SUBSCRIPTION=false
```

Por defecto está en `false`, lo que significa que todas las empresas activas pueden usar el API. Si lo cambias a `true`, solo las empresas con suscripción activa podrán usar el API.

## 🔒 Seguridad

### Mejores Prácticas

1. **Nunca compartas tokens**: Los tokens son como contraseñas
2. **Usa HTTPS**: Siempre usa conexiones seguras
3. **Restringe IPs**: Si es posible, restringe los tokens a IPs específicas
4. **Establece expiración**: No crees tokens sin fecha de expiración
5. **Rota tokens regularmente**: Regenera tokens periódicamente
6. **Monitorea el uso**: Revisa regularmente los logs de uso de tokens

### Permisos (Abilities)

Los permisos siguen un formato de punto:

- `*`: Todos los permisos
- `invoices.create`: Crear facturas
- `invoices.view`: Ver facturas
- `invoices.update`: Actualizar facturas
- `boletas.create`: Crear boletas
- `boletas.view`: Ver boletas
- etc.

## 🐛 Troubleshooting

### Error: "Token inválido"

- Verifica que el token esté correctamente copiado
- Verifica que el token no haya expirado
- Verifica que el token esté activo

### Error: "IP no autorizada"

- Verifica que tu IP esté en la lista de IPs permitidas del token
- Si no hay IPs configuradas, cualquier IP debería funcionar

### Error: "Límite de solicitudes excedido"

- Has alcanzado el límite diario o por minuto
- Espera hasta el siguiente día o minuto
- Contacta al administrador para aumentar los límites

### Error: "Empresa inactiva o sin suscripción válida"

- Verifica que la empresa esté activa
- Verifica que la empresa tenga una suscripción activa (si REQUIRE_SUBSCRIPTION=true)

## 📚 Referencias

- [Laravel Sanctum Documentation](https://laravel.com/docs/sanctum)
- [API Routes](./routes/api.php)
- [Company Model](./app/Models/Company.php)
- [CompanyApiToken Model](./app/Models/CompanyApiToken.php)
- [Subscription Model](./app/Models/Subscription.php)

