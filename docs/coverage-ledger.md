| Feature | Models / Endpoints | Tests |
| --- | --- | --- |
| Ticketing | Ticket & Message models; /api/v1/health, /api/v1/tickets/{ticket}/messages; Filament TicketResource & MessageResource | TicketCrudTest.php, FactoryTest.php, RbacPolicyTest.php, HealthcheckTest.php, MessageVisibilityTest.php |
| Contacts & Users | Contact, Company, User models; Filament ContactResource | ContactCrudTest.php, RbacPolicyTest.php, AuthTest.php |
| Knowledge Base | KbCategory, KbArticle models; Filament KbArticleResource | KbCrudTest.php |
| Automation & SLA | Queue config, jobs migration (scaffolding) | RbacPolicyTest.php (permissions groundwork) |
| AI | TBD placeholders (future integration) | TBD |
| Email | Attachment model, mail config | HealthcheckTest.php (ensures base pipeline) |
| Reporting & Analytics | AuditLog model, migrations | TenancyScopeTest.php |
| Integrations | Webhook model | FactoryTest.php |
| Admin & Portal | FilamentServiceProvider, resources | ContactCrudTest.php, TicketCrudTest.php |
| Security | Policies, AuthServiceProvider, session config | RbacPolicyTest.php, AuthTest.php |
| DevOps & Infra | Dockerfile, docker-compose, CI workflow, Makefile, config/meilisearch.php | HealthcheckTest.php (runtime), MeilisearchInfrastructureTest.php |
