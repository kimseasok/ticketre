# Meilisearch Infrastructure Runbook

> Scope: E3-F6-I1 — Configure Meilisearch infrastructure for knowledge base search.

## Overview
- Primary service: [Meilisearch v1.7](https://www.meilisearch.com/)
- Purpose: power Laravel Scout full-text search for knowledge base articles.
- Authentication: master key provided via `MEILISEARCH_KEY`.
- Observability: structured JSON logs with correlation IDs (`meilisearch.health_check.*`).
- Data retention: search indexes stored on the `meilisearch-data` volume.

## Provisioning Checklist
1. **Create service**
   - Deploy `getmeili/meilisearch:v1.7` (managed container or VM).
   - Mount persistent storage (minimum 5 GB) for `/meili_data`.
2. **Apply configuration**
   - Set environment variables:
     | Variable | Description |
     | --- | --- |
     | `MEILI_ENV` | `production` for managed deployments. |
     | `MEILI_MASTER_KEY` | 32+ character secret; rotate quarterly. |
     | `MEILI_NO_ANALYTICS` | `true` to disable telemetry. |
   - Optional: `MEILISEARCH_HEALTHCHECK_URL` (defaults to `http://127.0.0.1:7700/health`).
3. **Networking**
   - Restrict ingress to application subnets and CI runners.
   - Enforce TLS termination via ingress proxy or load balancer.
4. **Application configuration**
   - Populate `.env` or secret manager entries:
     ```env
     MEILISEARCH_HOST=https://search.<tenant-domain>
     MEILISEARCH_KEY=<master-key>
     MEILISEARCH_HEALTHCHECK_URL=https://search.<tenant-domain>/health
     MEILISEARCH_HEALTHCHECK_TIMEOUT=2
     MEILISEARCH_BACKUP_PATH=/var/backups/meilisearch
     MEILISEARCH_BACKUP_RETENTION_DAYS=14
     SCOUT_DRIVER=meilisearch
     SCOUT_QUEUE=true
     ```
   - Deploy `config/meilisearch.php` with application release.

## Backup & Restore
- **Schedule**: Nightly at 02:00 UTC via cron on the application runner or managed backup service.
- **Command**:
  ```bash
  curl -s -X POST "$MEILISEARCH_HOST/dumps" \
    -H "X-Meili-API-Key: $MEILISEARCH_KEY" \
    -H "X-Correlation-ID: meilisearch-backup-$(date -u +%Y%m%dT%H%M%SZ)" \
    -o "$MEILISEARCH_BACKUP_PATH/$(date -u +%Y%m%dT%H%M%SZ).dump"
  ```
- **Retention**: prune dumps older than `MEILISEARCH_BACKUP_RETENTION_DAYS` (default 14 days).
- **Restore Procedure**:
  1. Stop index writers (queue workers / HTTP traffic).
  2. Upload dump file and call `POST /dumps/<dump-id>/import` with master key.
  3. Re-enable writers and confirm health via `php artisan meilisearch:health-check`.

## Monitoring & Alerts
| Alert | Query | Threshold | Action |
| --- | --- | --- | --- |
| **Meilisearch Uptime** | `up{service="meilisearch"} == 0` (Prometheus) for 5 minutes | Critical page | Page on-call, run health command, failover to standby. |
| **Search Index Lag** | `max(meilisearch_kb_articles_processing) > 0` for 10 minutes | High | Inspect queue backlog, restart `queue:work`, trigger `php artisan scout:sync "App\\Models\\KbArticle"`. |
| **Backup Freshness** | `time() - max(meilisearch_backup_timestamp)` > 36h | Medium | Run backup job manually, validate storage quota. |
| **Error Rate** | `rate(http_requests_total{service="meilisearch",status=~"5.."}[5m]) > 1` | Medium | Review Meilisearch logs and upstream network health. |

> Metrics sources: Prometheus exporter (`/metrics`) or custom scrape via `GET /stats` per index. The alert names align with Grafana dashboards under **Knowledge Base Search**.

## Operational Runbook
1. **Routine health check**
   - The scheduler runs `php artisan meilisearch:health-check` every five minutes when Scout driver is Meilisearch.
   - Manual invocation: `php artisan meilisearch:health-check --timeout=3`.
   - Logs stream to `storage/logs/laravel.log` with correlation IDs.
2. **Scaling**
   - Increase CPU/RAM before sustained QPS spikes (> 50 req/s) by editing container resources.
   - Reindex: `php artisan scout:import "App\\Models\\KbArticle"` (queues by default).
3. **Disaster recovery**
   - If instance unrecoverable, provision new node, restore latest dump, reconfigure DNS/ingress.
   - Confirm search results via `/api/v1/kb-articles/search` smoke test.
4. **Security**
   - Rotate `MEILISEARCH_KEY` quarterly; update application secrets and restart queue workers.
   - Audit logs capture job dispatch and health checks with hashed identifiers.

## References
- Laravel Scout + Meilisearch docs: https://laravel.com/docs/scout
- Meilisearch operations: https://www.meilisearch.com/docs/learn/advanced/operations
- Internal observability dashboards: `grafana.example.com/d/kb-search`
