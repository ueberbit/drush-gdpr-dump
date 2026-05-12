# GDPR Dump Command for Drush

## Prerequisites

### Installation of gdpr-dump

This package depends on a fork of [gdpr-dump](https://github.com/webflo/gdpr-dump). The relevant dependency updates are not yet merged into the [original repository](https://github.com/Smile-SA/gdpr-dump).
Add the fork to your composer.json before installing ``ueberbit/drush-gdpr-dump`` package.

```
  "repositories": [
      {
          "type": "vcs",
          "url":  "https://github.com/webflo/gdpr-dump.git"
      }
  ],
```

See https://github.com/Smile-SA/gdpr-dump/pull/200 for more details.

### Patch for Drush

~~https://github.com/drush-ops/drush/pull/6524~~ committed to drush 13.x - no stable release yet.

## Installation

```
composer require ueberbit/drush-gdpr-dump
```

## Configuration

Add the gdpr-dump configuration to your project. It uses the same configuration as the original gdpr-dump.

see https://github.com/Smile-SA/gdpr-dump/wiki/Configuration-File

```yaml
# Filename: gdpr-config.yaml

# https://github.com/Smile-SA/gdpr-dump/wiki/Configuration-File
extends: 'drupal8'

tables:
  authmap:
    truncate: true

  batch:
    truncate: true

  captcha_sessions:
    truncate: true

  comment_field_data:
    converters:
      hostname:
        converter: 'setNull'

  users_data:
    where: 'module <> "openid_connect" and name <> "oidc_name"'

  users_field_data:
    converters:
      mail:
        converter: 'randomizeEmail'
        unique: true
      pass:
        converter: 'randomizeText'
      init:
        converter: 'setNull'

    # Skip anonymization for users registered with ueberbit.de email domain.
    skip_conversion_if: 'str_ends_with((string) {{mail}}, "@ueberbit.de")'

  watchdog:
    truncate: true
```

## Usage

```
# Dump sanitized database dump.

./vendor/bin/drush gdpr:dump > sanitized-dump.sql
```
