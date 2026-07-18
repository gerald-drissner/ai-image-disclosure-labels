# Publishing

GitHub is the development repository. WordPress.org SVN is the release repository.

## Versioning

Use semantic versioning.

For WordPress.org deployments, Git tags must match the `Stable tag` exactly:

```text
2.0
```

Do not use `v2.0` for this repository because the deployment action uses the Git tag as the WordPress.org SVN tag.

Update these values before every release:

1. `Version` in `ai-image-disclosure-labels.php`
2. `GD_AI_IMAGE_LABELS_VERSION`
3. `Stable tag` in `readme.txt`
4. `readme.txt` changelog
5. `CHANGELOG.md`

## GitHub repository secrets

Create these Actions secrets:

- `SVN_USERNAME` — `geralddrissner`
- `SVN_PASSWORD` — the WordPress.org SVN/application password

## Release workflow

1. Commit the final code to `main`.
2. Confirm the Plugin Check workflow passes.
3. Create and push the matching tag.
4. Publish a GitHub Release using that tag.
5. The deploy workflow sends the tagged code and `.wordpress-org` assets to WordPress.org and attaches a clean ZIP to the GitHub Release.
6. Confirm the pending WordPress.org release in the WordPress.org release-management interface or confirmation email.

## Fish commands

```fish
cd /home/gd/github/ai-image-disclosure-labels

git status
git add --all
git commit -m "Release 2.0"
git push origin main

git tag -a 2.0 -m "AI Image Disclosure & Labels 2.0"
git push origin 2.0

gh release create 2.0     --title "AI Image Disclosure & Labels 2.0"     --notes-from-tag
```

Publishing the GitHub Release triggers the WordPress.org deployment.
