# frontend-dependencies

A composer plugin to manage frontend dependencies (copied the idea from https://www.npmjs.com/package/frontend-dependencies).

## Install

``` bash
$ composer require vakata/frontend-dependencies
```

## Usage

In your ```composer.json``` file include the dependencies you need and where to copy them. Each dependency is either a name and a version, or an array consisting of a version and a glob pattern. You can additionally control whether the dependencies are updated on each install or update (defaults to ```true```) and whether a clean full install is performed every time.

**Keep in mind the ```target``` folder will be emptied every time dependencies are updated!**

``` json
"extra": {
    "vakata" : {
        "frontend-dependencies" : {
            "clean" : false,
            "install" : true,
            "update" : true,
            "target" : "public/assets/static/",
            "dependencies" : {
                "dep1" : "~1.0",
                "dep2" : {
                    "version" : "~1.0"
                },
                "dep3" : {
                    "version" : "~1.0",
                    "src" : "dist/*"
                },
                "dep4" : {
                    "version" : "~1.0",
                    "src" : "dist/dep4.js"
                },
                "dep5" : {
                    "version" : "~1.0",
                    "src" : "{dist/dep5.js,images}"
                }
            }
        }
    }
}
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email github@vakata.com instead of using the issue tracker.

## Credits

- [vakata][link-author]
- [All Contributors][link-contributors]

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

