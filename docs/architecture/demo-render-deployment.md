# NEXORA Demo — Render + Neon

This deployment is for the demo environment only. It must never use the EthioDeploy database or a production database.

## Separation

- Git repository: `JASBH121101/Nexora`
- Deployment branch: `feature/nexora-configurable-catalog`
- Render service name: `nexora-demo`
- Render plan: Free web service
- Database project: Neon `nexora-demo`
- Database name: `nexora_demo`
- Installation mode: `demo`

The production/EthioDeploy project and its environment variables are not referenced by this blueprint.

## Render Blueprint

The root `render.yaml` creates only a Docker-based web service. It does not create a Render database or a paid resource.

During initial Blueprint setup, Render asks for `DATABASE_URL`. Obtain the **pooled** connection string from the Neon project `nexora-demo` (database `nexora_demo`) and paste it directly into Render's secret field. Do not commit it to GitHub, put it in this file, or reuse a connection string from another project.

The Blueprint generates a separate `APP_KEY`. Keep the generated key in Render's environment.

## Demo limitations

- Render Free web services sleep after 15 minutes without traffic; the first request after sleep may take about a minute to wake.
- The service filesystem is ephemeral. Uploaded logos, covers, and other local files can disappear after sleep, restart, or deploy. Do not use this demo to store customer files.
- Render Free does not provide SSH or one-off jobs. Do not rely on running Artisan commands manually in the hosted container.
- Neon Free currently provides a per-project allowance of 100 CU-hours, 1 GB Postgres storage, and 5 GB monthly public network transfer. Compute scales to zero after inactivity. Monitor usage before doing extended tests.
- This is a temporary test environment, not production hosting or a customer-data environment.

## Deployment behavior

The service is linked to `feature/nexora-configurable-catalog`. `autoDeployTrigger: checksPass` tells Render to deploy only after linked Git checks pass. A push to this branch does not change the EthioDeploy service because Render is a separate project.

Do not change the EthioDeploy production branch or its environment variables as part of this demo setup.
