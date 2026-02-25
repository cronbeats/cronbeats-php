# Release Checklist

Use this for every new SDK release.

## Pre-release

- Run tests locally: `./vendor/bin/phpunit`
- Validate package metadata: `composer validate --strict`
- Confirm README examples still match current API
- Confirm `docs/`, `spec/`, and `contract/` are not staged

## Release

- Commit changes on `main`
- Create tag: `git tag vX.Y.Z`
- Push main branch
- Push tag: `git push origin vX.Y.Z`

## Post-release

- Click **Update** on Packagist package page
- Verify install from clean folder:
  - `composer require cronbeats/cronbeats-php:^X.Y`
- Verify expected version resolves
