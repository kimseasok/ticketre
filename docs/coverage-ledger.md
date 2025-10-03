| Feature | Models / Endpoints | Tests |
| --- | --- | --- |
| Ticketing | Ticket, Message, Attachment models; /api/v1/health (for platform), Filament TicketResource | TicketCrudTest.php, FactoryTest.php, RbacPolicyTest.php, HealthcheckTest.php |
| Contacts & Users | Contact, Company, User models; Filament ContactResource | ContactCrudTest.php, RbacPolicyTest.php, AuthTest.php |
| Knowledge Base | KbCategory, KbArticle models; Filament KbArticleResource | KbCrudTest.php |
| Automation & SLA | Queue config, jobs migration (scaffolding) | RbacPolicyTest.php (permissions groundwork) |
| AI | TBD placeholders (future integration) | TBD |
| Email | Attachment model, mail config | HealthcheckTest.php (ensures base pipeline) |
| Reporting & Analytics | AuditLog model, migrations | TenancyScopeTest.php |
| Integrations | Webhook model | FactoryTest.php |
| Admin & Portal | FilamentServiceProvider, resources | ContactCrudTest.php, TicketCrudTest.php |
| Security | Policies, AuthServiceProvider, session config | RbacPolicyTest.php, AuthTest.php |
| DevOps & Infra | Dockerfile, docker-compose, CI workflow, Makefile | HealthcheckTest.php (runtime), CI config |
