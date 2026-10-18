# DmLab_AdminSsoAzure

> Microsoft Entra ID (Azure AD) login for the Magento 2 admin panel.

[![CI](https://github.com/dmlab-work/module-admin-sso-azure/actions/workflows/ci.yml/badge.svg)](https://github.com/dmlab-work/module-admin-sso-azure/actions/workflows/ci.yml) [![codecov](https://codecov.io/gh/dmlab-work/module-admin-sso-azure/graph/badge.svg)](https://codecov.io/gh/dmlab-work/module-admin-sso-azure) [![Packagist Version](https://img.shields.io/packagist/v/dmlab/module-admin-sso-azure)](https://packagist.org/packages/dmlab/module-admin-sso-azure) [![Downloads](https://img.shields.io/packagist/dt/dmlab/module-admin-sso-azure)](https://packagist.org/packages/dmlab/module-admin-sso-azure) [![PHP](https://img.shields.io/packagist/dependency-v/dmlab/module-admin-sso-azure/php)](https://packagist.org/packages/dmlab/module-admin-sso-azure) [![License](https://img.shields.io/packagist/l/dmlab/module-admin-sso-azure)](LICENSE) ![Magento](https://img.shields.io/badge/Magento-2.4-orange)

A thin Microsoft Entra ID provider plugin for the provider-agnostic `admin-sso` capability. It supplies the Entra OIDC preset — discovery from your tenant, scopes, `groups` claim, login-button branding — while all OIDC protocol lives in `sso-core` and all admin logic in `admin-sso`. Installing it pulls `admin-sso` and `sso-core` automatically.

## Installation

```bash
composer require dmlab/module-admin-sso-azure
bin/magento module:enable DmLab_SsoCore DmLab_AdminSso DmLab_AdminSsoAzure
bin/magento setup:upgrade
```

## Register the Entra app

In the [Azure portal](https://portal.azure.com) → **Microsoft Entra ID → App registrations → New registration**:

1. **Supported account types** — **Accounts in this organizational directory only**
   (single tenant). Multi-tenant admin login is not supported (see Tenant below).
2. **Redirect URI** — platform **Web**, value = the `admin-sso` callback:
   `https://<admin-host>/<admin-path>/adminsso/sso/callback`
   (`<admin-path>` is the backend frontName, default `admin`). It must match the
   admin URL used at runtime exactly.
3. Register, then copy the **Application (client) ID** and the **Directory (tenant) ID**.
4. **Certificates & secrets → New client secret** — copy the secret **value**.
5. **Groups claim** — for IdP-group → ACL-role mapping, **Token configuration →
   Add groups claim**, and emit it on the **ID** token. Without this Entra sends no
   groups and role mapping falls back to the `admin-sso` default role (login refused
   if none is set). Entra sends group
   **object IDs (GUIDs)**, not names — use those GUIDs as keys in the `admin-sso`
   group → role map.
6. **Email verification** — **Token configuration → Add optional claim → ID →
   `xms_edov`**. Entra's `email` claim is tenant-editable, so it counts as verified only
   when `xms_edov` is true. Without this claim, signing in as an existing, not yet linked
   admin is refused.
7. **Restrict sign-in** — **Enterprise applications → (your app) → Properties →
   Assignment required? = Yes**, then assign users/groups under **Users and groups**.
   Otherwise any tenant user whose groups map to a role, or who gets the default role,
   can sign in.

## Configuration

Admin → Stores → Configuration → **DMLab → Admin SSO**.

**General** (`dmlab_admin_sso/general/*`):

| Field | Value |
|---|---|
| Enable Admin SSO | Yes |
| Identity Provider | Microsoft Entra ID |
| Client ID | Application (client) ID from the Entra app |
| Client Secret | client secret value from the Entra app |

**Microsoft Entra ID** (`dmlab_admin_sso/azure/*`, shown when Entra is selected):

| Field | Value |
|---|---|
| Tenant | Directory (tenant) GUID or a verified domain of your directory (single-tenant only) |

The discovery URL is derived from the tenant:
`https://login.microsoftonline.com/<tenant>/v2.0/.well-known/openid-configuration`.
Admin SSO is single-tenant only — the multi-tenant meta values `common`/`organizations`
advertise a templated `{tenantid}` issuer that never matches a real token, so they are
rejected.

A verified domain (e.g. `contoso.com`) works: its discovery document advertises the
tenant-GUID issuer, so the discovery issuer check is relaxed for domain tenants only (token
`iss` is still validated). The GUID is preferred.

Group → role mapping and enforce-SSO/break-glass are configured in `admin-sso`; see
that module's README.

### Users without an email

If a user has no `mail` attribute (no `email` claim), `preferred_username`, then `upn`, is
used as the email when it is a valid address. Such an identity is always unverified: it
can JIT-create a new admin; if an admin with that email already exists, the login is refused.

### Group overage limitation

Entra omits the `groups` claim when a user belongs to more than ~200 groups, sending
a `_claim_names`/`_claim_sources` overage reference to Microsoft Graph instead. The
module reads the `groups` claim directly, so such users get no group-based roles and fall
back to the default role (login refused if none is set). A Graph fallback is a future task. Keep affected admins
under the overage limit, or scope the groups claim (Token configuration) to the
groups assigned to the application.

## Requirements

- Magento **2.4.x**
- PHP **8.3 – 8.5**

## Part of the DMLab identity suite

| Repo | Role |
|------|------|
| `sso-core` | Shared OIDC engine (installed automatically) |
| `admin-sso` · `admin-sso-<idp>` | Admin-panel SSO login |
| `customer-sso` · `customer-sso-<idp>` | Storefront SSO login |
| `admin-scim` · `admin-scim-<idp>` | Admin-user provisioning (SCIM 2.0) |

## License

[OSL-3.0](LICENSE) © DMLab. Commercial licensing and support: <https://dmlab.work>.
