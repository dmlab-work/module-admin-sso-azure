# Changelog

## 0.2.1

- **Groups overage refuses the login** with a message (was: fall back to the default role); the implicit-flow `hasgroups` claim is handled too.
- Requires `dmlab/module-admin-sso ^0.2.2` / `dmlab/module-sso-core ^0.2.2`.

## 0.2.0

- **Verified email from `xms_edov`**: Entra's `email` claim counts as verified only when the `xms_edov` optional claim is true; `email_verified` is ignored. Without `xms_edov` existing admins are never adopted by email.
- **Verified-domain tenants**: the discovery issuer check is relaxed when Tenant is a domain (its issuer carries the tenant GUID); a GUID tenant still enforces it.
- **JIT email fallback**: users without a `mail` attribute get `preferred_username` / `upn` as email, always unverified — creates an admin, never adopts one.
- Requires `dmlab/module-admin-sso ^0.2` / `dmlab/module-sso-core ^0.2`. Packaging: `dmlab/module-sso-core` and `magento/module-config` declared, `DmLab_SsoCore` sequenced, OSL-3.0 headers, i18n phrases.
