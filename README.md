## How to use

install byteShard schema

`composer require byteshard/schema`

#### Create/migrate database
Make sure your database connection is entered in the /config.php file in the root directory.

Then call `./vendor/bin/database` to execute the migration.


## Development

Clone repository, execute `composer update`. Due to the dev-dependency to `byteshard/core` and it's dependency back to this package, composer needs to know the current package version. This can be achieved by setting `COMPOSER_ROOT_VERSION=$(git describe --tags --abbrev=0)`. In a docker environment the following command achieves the same: `docker run --rm --interactive --tty --volume $PWD:/app --volume ~/.composer:/tmp -e COMPOSER_ROOT_VERSION=$(git describe --tags --abbrev=0)  composer`.


## Contribution

Please read our [Contribution Guide](CONTRIBUTE.md).

## License

The license can be found [here](LICENSE)