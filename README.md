![Build status](https://github.com/marvincaspar/composer-azure-plugin/workflows/CI/badge.svg)
[![codecov](https://codecov.io/gh/marvincaspar/composer-azure-plugin/branch/master/graph/badge.svg?token=DG3G63T0Q2)](https://codecov.io/gh/marvincaspar/composer-azure-plugin)

# Composer Azure Plugin

Composer Azure plugin is an attempt to use Composer with Azure DevOps artifacts, via universal packages.

## Install

Composer Azure Plugin requires [Composer 1.0.0](https://getcomposer.org/) or newer. It should be installed globally.

```
$ composer global require marvincaspar/composer-azure-plugin
```

You have to be logged in via
the [Azure command line interface](https://docs.microsoft.com/fr-fr/cli/azure/?view=azure-cli-latest).

## Usage

This plugin has two components. Publishing a composer package to azure and pulling the dependency.

### Publishing a package

In the package you want to publish you have to add an `azure-publish-registry` config to the `extra` block.

```json
{
  ...
  "extra": {
    "azure-publish-registry": {
      "organization": "<my-organization>",
      "project": "<my-project-name>",
      "feed": "<my-feed-name>"
    }
  }
}
```

This plugin adds a new composer command to easily publish the package. Just run `composer azure:publish` and it will
remove all ignore files (e.g. the vendor folder) and publish the code to azure artifacts.

### Use package as dependency

To use a published package add an `azure-repositories` config to the `extra` block. There you define which packages are
required for the current project. In the `required` block you then define the requirements as usual. The only downsite
is, that you can't use constraints and set a specific version.

```json
{
  "require": {
    "vendor-name/my-package": "1.0.0"
  },
  "extra": {
    "azure-repositories": [
      {
        "organization": "<my-organization>",
        "project": "<my-project-name>",
        "feed": "<my-feed-name>",
        "symlink": false,
        "packages": [
          "vendor-name/my-package"
        ]
      }
    ]
  }
}
```