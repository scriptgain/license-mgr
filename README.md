# LicenseMGR

**Issue and enforce licence keys for software you sell.** Self-hosted, by
[ScriptGain](https://scriptgain.com).

**[Try the live demo →](https://license-demo.scriptgain.com)** No signup required.

## Who it's for

Anyone selling software that needs to check whether the copy running is paid for:
desktop applications, self-hosted web apps, plugins, WordPress themes, game
servers, or firmware. If you are currently doing this with a spreadsheet of keys
and an honour system, this replaces both.

## What it does

**Define what you sell**
Products, plans, and features. A licence carries the features its plan includes, so
your application can ask "is reporting enabled for this key" instead of hardcoding
tiers.

**Issue keys**
Generate keys in your own format, individually or in bulk, tied to a customer.

**Enforce activation limits**
Each licence has a seat count. Activations record which machine, host, or domain
used the key, so a licence sold for one server cannot quietly run on nine. Revoke
an activation and the seat frees up.

**Answer your app's questions**
Your software calls the API to validate a key, activate a machine, and check
feature entitlements. Signed responses so a client can verify what came back.

**Handle the awkward cases**
Customers, locations, and licence servers for on-premise customers who cannot reach
the internet.

**Run it like production**
Users and roles, two-factor authentication, an IP firewall with an escape hatch,
API tokens, a full audit log, database backups, host and SSL settings, and
in-place signed updates.

## Current state

**Version 1.1.2.** In production use. This is the system ScriptGain uses to
license its own products, so the validation, activation, and entitlement paths are
exercised daily rather than only in tests.

## One thing to know about statuses

A licence's real status resolves through its status record's behaviour, not through
a free-text field. If you integrate directly against the database rather than the
API, check the resolved status; the plain text column is a label, not the authority.
The API always returns the resolved answer.

## Install

Point a fresh Debian or Ubuntu server at your domain and run, as root:

```
curl -fsSL https://install.scriptgain.com | sudo bash -s -- license-mgr DOMAIN=licenses.example.com SSL=1 EMAIL=you@example.com
```

Then open `https://your.domain/setup` to create the first account and enter your
licence key. Yes, LicenseMGR is itself licensed.

## Where things live

| Surface | Path |
| --- | --- |
| Console | `/` |
| Validation and activation API | `/api` |
| First-run setup | `/setup` |

## Running it

Products, plans, features, keys, and every operator setting are managed in the
console rather than in files on the server.

Maintenance tasks from the command line:

| Command | What it does |
| --- | --- |
| `php artisan license:maintenance` | Expires lapsed licences and trims the audit log. |
| `php artisan license:check-online` | Re-validates this instance's own licence. |
| `php artisan app:update` | Applies a signed release. |
| `php artisan db-backup:run` | Backs up the database. |
| `php artisan firewall:clear` | Gets you back in if an IP rule locks you out. |

## Requirements

A Linux server with PHP 8.3 and MySQL or MariaDB. Licence checks are small and
frequent, so put it somewhere with reliable uptime, because your customers' software
depends on it answering.

## Licensing

One activation per licence by default, validated against
`https://scriptgain.com/v1`. Buy or manage yours at
[scriptgain.com/products/licensemanager](https://scriptgain.com/products/licensemanager).
