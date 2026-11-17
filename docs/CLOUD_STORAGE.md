# LearnCambodia managed file storage

Cloud mode uses Cloudflare R2's S3 API. The application keeps the same file keys and URLs for Media Library items, course and lesson files, support attachments, account avatars, and speaking assessment recordings. The bucket is private. Laravel authorizes each request before serving it, including audio and video range requests.

## Configure the bucket

Create a private bucket dedicated to LearnCambodia and create an Object Read & Write credential scoped to that bucket. Use the S3 endpoint shown in the R2 dashboard. Add these settings to the aaPanel site's private `.env` and keep the credential values out of Git, shell history, and deployment output.

```dotenv
MANAGED_STORAGE_DISK=local
R2_BUCKET=learncambodia-private
R2_ENDPOINT=https://<account-id>.r2.cloudflarestorage.com
R2_REGION=auto
R2_ACCESS_KEY_ID=
R2_SECRET_ACCESS_KEY=
```

The bucket name above is an example. Leave Laravel's global `FILESYSTEM_DISK` set to its existing value. Reload cached configuration after editing the settings.

## Inventory, copy and cut over

Run these as the web service account from the live project root. The aaPanel PHP path below follows the project's operator scripts.

```bash
cd /www/wwwroot/learncambodia.com
artisan() { runuser -u www -- /www/server/php/85/bin/php artisan "$@" --no-interaction; }

artisan config:cache
artisan storage:managed-transfer
artisan storage:managed-transfer --mode=probe
artisan storage:managed-transfer --mode=copy
```

The default mode counts files, bytes and missing database references. Probe mode writes, reads and deletes one temporary object. Copy mode reuses matching copies and reads both sides to compare SHA-256. Conflicting destination files require investigation and are left in place. An unsuccessful command exits nonzero.

Use maintenance mode for the final copy. Pause any imports or queue workers that write files and let in-flight uploads complete before the last pass.

```bash
artisan down --retry=60
artisan storage:managed-transfer --mode=copy
artisan storage:managed-transfer --mode=verify
```

Once both commands exit successfully with zero errors, set `MANAGED_STORAGE_DISK=r2` in `.env`, run `artisan config:cache`, and run `artisan up`. If verification fails, keep the local setting and run `artisan up` to restore service while resolving the reported objects. Local source copies are retained by the transfer.

After cutover, upload and retrieve a library file as an administrator, play and seek in an audio or video lesson, and check an avatar and a submitted speaking response using an authorized account. Confirm a different account is denied access to private media.

## Roll back file storage

Enter maintenance mode and let active writers finish. While cloud credentials are still configured, copy and verify in the opposite direction.

```bash
artisan storage:managed-transfer --mode=copy --direction=to-local
artisan storage:managed-transfer --mode=verify --direction=to-local
```

After both commands pass, set `MANAGED_STORAGE_DISK=local`, cache the configuration, and bring the site back up. Cloud writes after the original cutover are included in this reverse transfer. Switching back without that transfer can leave new records pointing to files missing locally.

## Database and server backups

The transfer covers the three application-managed directories in `storage/app/private`. Laravel runtime state, logs, deployment markers, source-controlled assets and configuration are managed on the application host. MySQL continues serving live relational data.

To retain database data in R2, first export the MySQL backup over the existing backup workflow and encrypt it to an independently stored recovery key. Upload the encrypted archive to a restricted backup prefix or a separate private bucket. Use a dedicated backup credential and test decryption and restore against a disposable database. For example, with an existing `rclone` R2 remote using bucket-scoped credentials:

```bash
# stamp must be unique for each completed dump.
archive="/root/learncambodia-backups/learncambodia-${stamp}.sql.gz.age"
object="r2-backups:learncambodia-backups/database/${archive##*/}"
rclone copyto --immutable "$archive" "$object"
test "$(rclone cat "$object" | sha256sum | cut -d' ' -f1)" = "$(sha256sum "$archive" | cut -d' ' -f1)"
```

Run the backup verification with Bash `set -o pipefail`. Store the backup decryption key independently of the bucket and production application.


## Amazon S3 and dashboard transfers

Set AWS_BUCKET and AWS_DEFAULT_REGION for a private general-purpose S3 bucket. Use a role or the AWS SDK credential chain when available. For an aaPanel host without a role, use bucket-scoped AWS_ACCESS_KEY_ID and AWS_SECRET_ACCESS_KEY. Set AWS_SESSION_TOKEN for temporary credentials. The S3 bucket must permit listing its prefix and reading, writing, and deleting probe objects. Keep S3 bucket public access blocked. The managed S3 disk uses the standard AWS endpoint and does not change Laravel's global S3 disk.

Cloud Storage in System Settings can test either configured bucket, queue a local copy, and queue checksum verification. Run the dedicated worker as the site's PHP user:

    runuser -u www -- /www/server/php/85/bin/php /www/wwwroot/learncambodia.com/artisan queue:work managed-storage --queue=managed-storage --timeout=1800 --tries=1 --stop-when-empty

Use a supervised worker with the same queue options (omit --stop-when-empty) if administrators need transfers to start automatically. Check the results on the same settings page. An operator can run the same storage command with --provider=s3 or --provider=r2 for a maintenance-window cutover. Once the final copy and verification pass in maintenance mode, set MANAGED_STORAGE_DISK=s3 for S3 or MANAGED_STORAGE_DISK=r2 for R2, rebuild cached configuration, and bring the site back online. For a return to local storage, follow the reverse-transfer instructions and include the provider that holds the current uploads.
