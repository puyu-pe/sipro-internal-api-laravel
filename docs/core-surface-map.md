# Core Surface Map (anti-alucinación)

Fuente inspeccionada: `vendor/puyu-pe/sipro-internal-api-core` (ref instalada: `dev-main 5e57d6f`).

## 1) Clases del core usadas por el bridge (FQCN exactos)

- DTOs
  - `PuyuPe\SiproInternalApiCore\Contracts\Dto\CreateTenantRequest`
  - `PuyuPe\SiproInternalApiCore\Contracts\Dto\WarnTenantRequest`
  - `PuyuPe\SiproInternalApiCore\Contracts\Dto\SuspendTenantRequest`
  - `PuyuPe\SiproInternalApiCore\Contracts\Dto\ActivateTenantRequest`
- Validación
  - `PuyuPe\SiproInternalApiCore\Contracts\Validation\ValidationResult`
- HMAC
  - `PuyuPe\SiproInternalApiCore\Security\Hmac\HmacSigner`
  - `PuyuPe\SiproInternalApiCore\Security\Hmac\HmacVerifier`
  - `PuyuPe\SiproInternalApiCore\Security\Hmac\CanonicalRequest`
  - `PuyuPe\SiproInternalApiCore\Security\Hmac\VerificationResult`
  - `PuyuPe\SiproInternalApiCore\Security\Hmac\NonceStoreInterface`
- Errores / respuestas
  - `PuyuPe\SiproInternalApiCore\Errors\ErrorCode`
  - `PuyuPe\SiproInternalApiCore\Errors\InternalApiError`
  - `PuyuPe\SiproInternalApiCore\Errors\ErrorFactory`
  - `PuyuPe\SiproInternalApiCore\Http\Response\ErrorResponse`
- Headers
  - `PuyuPe\SiproInternalApiCore\Http\InternalHeaders`

## 2) Firmas reales de métodos

### DTOs y validación
- `CreateTenantRequest::fromArray(array $payload): self`
- `WarnTenantRequest::fromArray(array $payload): self`
- `SuspendTenantRequest::fromArray(array $payload): self`
- `ActivateTenantRequest::fromArray(array $payload): self`
- `*Request->validate(): ValidationResult`
- `ValidationResult::success(): self`
- `ValidationResult::failure(array $errors): self`
- `ValidationResult->ok(): bool`
- `ValidationResult->errors(): array`

### HMAC
- `HmacSigner->sign(string $canonicalString, string $secret, string $algo = 'sha256', string $output = 'hex'): string`
- `HmacSigner->buildSignedHeaders(string $method, string $path, string $rawBody, string $keyId, string $secret, ?string $timestampNow = null, ?string $nonce = null, string $algo = 'sha256', string $output = 'hex'): array<string,string>`
- `HmacVerifier->verify(string $method, string $path, string $rawBody, array $headers, callable $resolveSecretByKeyId, ?NonceStoreInterface $nonceStore = null): VerificationResult`
- `CanonicalRequest::build(string $method, string $path, string $timestamp, string $nonce, string $rawBody): string`
- `CanonicalRequest::bodySha256Hex(string $rawBody): string`
- `NonceStoreInterface->has(string $nonce): bool`
- `NonceStoreInterface->put(string $nonce, int $ttlSeconds): void`
- `VerificationResult` (props públicas readonly):
  - `bool $ok`
  - `?ErrorCode $errorCode`
  - `?string $errorMessage`
  - `array $details`

### Errores / respuestas
- `new InternalApiError(ErrorCode $code, string $message, array $details = [])`
- `ErrorFactory::validationError(ValidationResult $vr): InternalApiError`
- `ErrorFactory::invalidSignature(): InternalApiError`
- `ErrorFactory::requestExpired(): InternalApiError`
- `ErrorFactory::nonceReplay(): InternalApiError`
- `ErrorFactory::tenantNotFound(string $tenantUuid): InternalApiError`
- `ErrorFactory::tenantAlreadyExists(string $tenantUuid): InternalApiError`
- `ErrorFactory::provisionFailed(?string $reason = null, array $details = []): InternalApiError`
- `ErrorResponse::fromError(InternalApiError $error): ErrorResponse`
- `ErrorResponse->toArray(): array`

### Constantes de headers
- `InternalHeaders::KEY_ID = 'X-Internal-KeyId'`
- `InternalHeaders::SIGNATURE = 'X-Internal-Signature'`
- `InternalHeaders::TIMESTAMP = 'X-Internal-Timestamp'`
- `InternalHeaders::NONCE = 'X-Internal-Nonce'`

## 3) Decisiones de integración fijadas

- **Path canónico**: usar `Request::getPathInfo()` (solo path, sin query). El core además vuelve a cortar query en `CanonicalRequest::build`.
- **Body exacto**: usar `Request::getContent()` sin re-encodear JSON (el hash SHA-256 se hace sobre raw body exacto).
- **Headers para verify**: pasar `array<string,mixed>` con keys de `InternalHeaders::*`; el core normaliza a lowercase + trim y exige keyId/timestamp/nonce/signature.
- **Secreto por keyId**: `HmacVerifier->verify` espera **callable** `fn(string $keyId): ?string`.
- **Firma aceptada**: el verifier acepta firma `sha256` en **hex o base64** (compara ambos).
- **Nonce store**: el core compone la clave como `"{keyId}:{nonce}"` antes de llamar `NonceStoreInterface`.

## 4) Diferencias detectadas vs prompts (alineación)

- El namespace real de DTOs es `PuyuPe\SiproInternalApiCore\Contracts\Dto\*` (no `...\Dto\*` sin `Contracts`).
- `validate()` **no lanza** por defecto: retorna `ValidationResult`; el bridge debe revisar `->ok()`.
- `ErrorFactory::validationError(...)` recibe `ValidationResult`, no `(message, details)`.
- `HmacVerifier->verify(...)` retorna `VerificationResult` (con `ok/errorCode/errorMessage/details`), no `bool` ni excepción por defecto.
- `NonceStoreInterface` usa parámetro `string $nonce` (semánticamente llega `keyId:nonce` desde el verifier).
- `ErrorCode` incluye: `INVALID_SIGNATURE`, `REQUEST_EXPIRED`, `NONCE_REPLAY`, `VALIDATION_ERROR`, `TENANT_NOT_FOUND`, `TENANT_ALREADY_EXISTS`, `PROVISION_FAILED`, `DB_CREATE_FAILED`, `TEMPLATE_APPLY_FAILED`.

