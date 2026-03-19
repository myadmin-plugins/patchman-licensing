# MyAdmin PatchMan Licensing

PatchMan licensing integration for the [MyAdmin](https://github.com/detain/myadmin) control panel. This package provides automated license provisioning, activation, deactivation, and IP management for [PatchMan](https://www.patchman.com/) security scanning services.

[![Build Status](https://github.com/detain/myadmin-patchman-licensing/actions/workflows/tests.yml/badge.svg)](https://github.com/detain/myadmin-patchman-licensing/actions/workflows/tests.yml)
[![Latest Stable Version](https://poser.pugx.org/detain/myadmin-patchman-licensing/version)](https://packagist.org/packages/detain/myadmin-patchman-licensing)
[![Total Downloads](https://poser.pugx.org/detain/myadmin-patchman-licensing/downloads)](https://packagist.org/packages/detain/myadmin-patchman-licensing)
[![License](https://poser.pugx.org/detain/myadmin-patchman-licensing/license)](https://packagist.org/packages/detain/myadmin-patchman-licensing)

## Features

- License activation and deactivation via the PatchMan API
- IP address change management for existing licenses
- Event-driven architecture using Symfony EventDispatcher
- Admin menu integration for license management
- Automatic invoice and billing integration

## Requirements

- PHP 8.2 or higher
- ext-curl

## Installation

```sh
composer require detain/myadmin-patchman-licensing
```

## Testing

```sh
composer install
vendor/bin/phpunit
```

## License

LGPL-2.1-only. See [LICENSE](https://www.gnu.org/licenses/old-licenses/lgpl-2.1.html) for details.
