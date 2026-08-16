# Publishing

GitHub is the development repository. WordPress.org SVN is the release repository.

## Versioning

Use semantic versioning.

For WordPress.org deployments, the Git tag must match the `Stable tag` exactly. For example:

```text
3.0.0
```

Do not prefix the tag with `v` in this repository because the deployment action uses the Git tag as the WordPress.org SVN tag.

Update these values before every release:

1. `Version` in `ai-image-disclosure-labels.php`
2. `GDAIIDL_VERSION` in `ai-image-disclosure-labels.php`
3. `Stable tag` in `readme.txt`
4. `readme.txt` changelog
5. `CHANGELOG.md`
6. `README.md` if features or requirements changed
7. `RELEASE_NOTES_<version>.md` and the release audit when release behavior or safeguards changed

## GitHub repository secrets

The deploy workflow requires these Actions secrets:

- `SVN_USERNAME` — WordPress.org SVN username
- `SVN_PASSWORD` — WordPress.org SVN-specific/application password

## Release workflow

1. Commit the final code to `main`.
2. Confirm the **WordPress Plugin Check** workflow passes.
3. Create and push the matching version tag.
4. Publish a GitHub Release using that exact tag.
5. The deploy workflow sends the tagged distribution files and `.wordpress-org` assets to WordPress.org and attaches a clean ZIP to the GitHub Release.
6. Complete any WordPress.org release-confirmation / rollout step requested by WordPress.org.

## Fish commands

```fish
cd /home/gd/github/ai-image-disclosure-labels

git status
git add --all
git commit -m "Release 3.0.0"
git push origin main

# Wait for Plugin Check to pass before tagging.
gh run list --branch main --limit 5

git tag -a 3.0.0 -m "AI Image & Video Disclosure Labels 3.0.0"
git push origin 3.0.0

gh release create 3.0.0 \
  --title "AI Image & Video Disclosure Labels 3.0.0" \
  --notes-file RELEASE_NOTES_3.0.0.md
```

Publishing the GitHub Release triggers the existing `.github/workflows/deploy-wordpress.yml` workflow. That workflow uses `10up/action-wordpress-plugin-deploy@stable` with `SLUG=ai-image-disclosure-labels`, pushes the release to the WordPress.org SVN repository, synchronizes `.wordpress-org` assets, and attaches the generated plugin ZIP to the GitHub Release. Do not manually commit a second copy to SVN after a successful workflow run.

## Verify WordPress.org SVN after deployment

```fish
set VERSION 3.0.0
set SVN_URL https://plugins.svn.wordpress.org/ai-image-disclosure-labels

svn cat "$SVN_URL/trunk/readme.txt" | grep -F "Stable tag: $VERSION"
svn cat "$SVN_URL/trunk/ai-image-disclosure-labels.php" | grep -F "Version:           $VERSION"
svn list "$SVN_URL/tags/$VERSION/"
```

WordPress.org requires tagged releases and the matching `Stable tag` in `trunk/readme.txt`. If Release Confirmation is enabled for the plugin, confirm the pending release in the WordPress.org Release Management dashboard after the SVN tag appears.
