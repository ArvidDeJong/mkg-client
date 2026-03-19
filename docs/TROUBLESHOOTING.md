# Troubleshooting

## Missing MKG config values

If you see `Missing MKG configuration values: ...`, verify your `.env` values and clear config cache:

```bash
php artisan config:clear
```

## SSL certificate issues in local/dev

If your MKG environment uses a self-signed certificate, use:

```dotenv
MKG_VERIFY_SSL=false
```

Keep `MKG_VERIFY_SSL=true` in production.

## Security notes

- Treat `MKG_PASSWORD` as a secret
- Prefer environment-based secret storage
- Keep TLS verification enabled in production
