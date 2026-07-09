# MageDevGroup_AdminSsoAzure

> Microsoft Entra ID (Azure AD) login for the Magento 2 admin panel.

![License](https://img.shields.io/badge/license-OSL--3.0-green) ![Magento](https://img.shields.io/badge/Magento-2.4-orange) ![PHP](https://img.shields.io/badge/PHP-8.3--8.5-blue) ![Version](https://img.shields.io/badge/version-0.0.1-lightgrey)

A thin Microsoft Entra ID provider plugin for the provider-agnostic `admin-sso` capability. It supplies the Entra OIDC preset — discovery from your tenant, scopes, `groups` claim, login-button branding — while all OIDC protocol lives in `sso-core` and all admin logic in `admin-sso`. Installing it pulls `admin-sso` and `sso-core` automatically.

## Installation

```bash
composer require magedevgroup/module-admin-sso-azure
bin/magento module:enable MageDevGroup_SsoCore MageDevGroup_AdminSso MageDevGroup_AdminSsoAzure
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
   groups and role mapping falls back to the default role.

## Configuration

Admin → Stores → Configuration → **MageDevGroup → Admin SSO**.

**General** (`magedevgroup_admin_sso/general/*`):

| Field | Value |
|---|---|
| Enable Admin SSO | Yes |
| Identity Provider | Microsoft Entra ID |
| Client ID | Application (client) ID from the Entra app |
| Client Secret | client secret value from the Entra app |

**Microsoft Entra ID** (`magedevgroup_admin_sso/azure/*`, shown when Entra is selected):

| Field | Value |
|---|---|
| Tenant | Directory (tenant) GUID or a verified domain of your directory (single-tenant only) |

The discovery URL is derived from the tenant:
`https://login.microsoftonline.com/<tenant>/v2.0/.well-known/openid-configuration`.
Admin SSO is single-tenant only — the multi-tenant meta values `common`/`organizations`
advertise a templated `{tenantid}` issuer that never matches a real token, so they are
rejected.

Group → role mapping and enforce-SSO/break-glass are configured in `admin-sso`; see
that module's README.

### Group overage limitation

Entra omits the `groups` claim when a user belongs to more than ~200 groups, sending
a `_claim_names`/`_claim_sources` overage reference to Microsoft Graph instead. v1
reads the `groups` claim directly, so such users get no group-based roles and fall
back to the default role. A Graph fallback is a future task. Keep affected admins
under the overage limit, or scope the groups claim (Token configuration) to the
groups assigned to the application.

## Requirements

- Magento **2.4.x**
- PHP **8.3 – 8.5**

## Part of the MageDevGroup identity suite

| Repo | Role |
|------|------|
| `sso-core` | Shared OIDC engine (installed automatically) |
| `admin-sso` · `admin-sso-<idp>` | Admin-panel SSO login |
| `customer-sso` · `customer-sso-<idp>` | Storefront SSO login |
| `admin-scim` · `admin-scim-<idp>` | Admin-user provisioning (SCIM 2.0) |

## License

[OSL-3.0](LICENSE) © MageDevGroup. Commercial licensing and support: <https://magedevgroup.com>.
