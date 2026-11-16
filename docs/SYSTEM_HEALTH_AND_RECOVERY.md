# Website health monitoring and recovery

## Current in-app checks

The Admin > Website health page runs explicit, bounded probes for the configured database, cache store, default filesystem disk, and database queue table (when the database queue driver is selected). Results are retained for 30 days. Error details, credentials, SQL text and filesystem paths are not rendered in the dashboard; protected application logs retain diagnostic exceptions.

These checks are manual in this first delivery slice. They do not automatically restart hosting services, run migrations, clear all caches, retry arbitrary jobs, or alter learning/payment data.

## cPanel and external uptime monitoring

Laravel exposes the lightweight application liveness route at /up. Configure an independent uptime monitor outside the hosting account to request https://YOUR-DOMAIN/up every 1–5 minutes and alert at least two independent contacts after consecutive failures. Because the monitor runs outside cPanel, it can still report a full server outage.

For scheduled heartbeat monitoring, configure the hosting cron to run Laravel's scheduler every minute:

    * * * * * cd /home/CPANEL_USER/path-to-lms && php artisan schedule:run >> /dev/null 2>&1

Replace the path and PHP executable with the values supplied by the hosting provider. Do not expose Artisan commands, queue controls, logs, or environment files over HTTP.

## Recovery boundaries

A Laravel application cannot repair a stopped web server, exhausted hosting account, inaccessible database host, DNS failure, or provider/network outage from inside that same failed hosting environment. Those incidents require external alerts and hosting-provider intervention.

Future automated recovery actions must be allowlisted, bounded, rate-limited, reversible, audited and protected by cooldown/circuit-breaker controls. Never auto-run migrations or modify/delete payments, enrollments, student records or course data.


## Scheduled dependency checks and email alerts

The Laravel scheduler runs \`system:heartbeat\` and \`system:health-check\` every five minutes. The health command probes the database, cache, default storage and configured database queue table, records a bounded result, and attempts one database reconnect if the first database probe fails. Email alerts are rate-limited with a file-cache cooldown.

Set these environment values in the cPanel application's environment configuration:

- \`SYSTEM_HEALTH_ALERT_EMAILS=admin@example.com,backup@example.com\` — comma-separated alert recipients.
- \`SYSTEM_HEALTH_ALERT_COOLDOWN_MINUTES=30\` — minimum alert interval (values below five minutes are clamped).

Ensure Laravel mail is configured and the \`storage/framework/cache/data\` directory is writable. If the database is unavailable, the command may be unable to persist the incident in the LMS; the separately configured email alert can still report it. The independent external uptime monitor remains necessary for total server outages.

The scheduled command performs only bounded probes and a single database reconnect attempt. It does not restart server processes, clear all caches, replay arbitrary jobs, or change business records.
