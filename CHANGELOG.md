## [1.3.4](https://github.com/byteshard/schema/compare/v1.3.3...v1.3.4) (2024-04-24)


### Bug Fixes

* update base schema to be compatible with new interfaces and remove authentication target logic ([ef5abf7](https://github.com/byteshard/schema/commit/ef5abf7eddfee399052f3ce9342f58b9f70802d9))
* use latest core version ([b99cba7](https://github.com/byteshard/schema/commit/b99cba7792f06e46a2bc95ee308a7dc9f232b21a))

## [1.3.3](https://github.com/byteshard/schema/compare/v1.3.2...v1.3.3) (2024-03-19)


### Bug Fixes

* longtext on mySQL was not correctly implemented ([ab732fa](https://github.com/byteshard/schema/commit/ab732fae56f29e1310d375ef0296a37e505b59f3))
* update github CI to be compatible with node 20 ([03af44f](https://github.com/byteshard/schema/commit/03af44f3470e380004f453ad3fcd3f546820df03))

## [1.3.2](https://github.com/byteshard/schema/compare/v1.3.1...v1.3.2) (2023-10-19)


### Bug Fixes

* default values for date, datetime and empty strings was not according to mysql definition ([e308a5a](https://github.com/byteshard/schema/commit/e308a5a6b61f332deb7518b77de6ecc310649dfc))

## [1.3.1](https://github.com/byteshard/schema/compare/v1.3.0...v1.3.1) (2023-10-18)


### Bug Fixes

* mysql cannot have a default value for auto increment columns ([e57ee0b](https://github.com/byteshard/schema/commit/e57ee0b942e915ce80b3876c9398ae8e95ed86fe))

# [1.3.0](https://github.com/byteshard/schema/compare/v1.2.2...v1.3.0) (2023-10-17)


### Bug Fixes

* don't show empty string values in schema, instead set a default for it in case the column is not nullable ([eb894cb](https://github.com/byteshard/schema/commit/eb894cb1a27f3a8ba0c1fd325ff6e6fc045a2936))
* fix default value for numeric columns which are not nullable ([49ed72c](https://github.com/byteshard/schema/commit/49ed72c16123281b43c53c11e6537499cdd513da))
* fix default value for string columns with an empty string ([a5253e6](https://github.com/byteshard/schema/commit/a5253e6c2f1c8ccd9825cfd4cd908a19b865d7fd))
* move default for nullable to database specific implementation. ([93a5fcd](https://github.com/byteshard/schema/commit/93a5fcdbf0a59b859b0ca468b53b5afe11b8e123))


### Features

* foreign key support for mysql ([f5e36c3](https://github.com/byteshard/schema/commit/f5e36c31594e7a385126a14bbd01f56da745be7b))
* foreign key support for mysql ([b7f1c1a](https://github.com/byteshard/schema/commit/b7f1c1a4c17df62e8009c08a9dc9ab1e7bfe57d8))

## [1.2.2](https://github.com/byteshard/schema/compare/v1.2.1...v1.2.2) (2023-10-13)


### Bug Fixes

* fix length handling for numeric columns and boolean ([6019273](https://github.com/byteshard/schema/commit/60192735176c0ebd0a37fce621cf01debbc4dc09))
* primary key matching was broken ([f374045](https://github.com/byteshard/schema/commit/f37404510bbef42786a2fe1778bc5b8eb778faa3))

## [1.2.1](https://github.com/byteshard/schema/compare/v1.2.0...v1.2.1) (2023-10-11)


### Bug Fixes

* correctly evaluate column grants ([cc1c67b](https://github.com/byteshard/schema/commit/cc1c67b8030247b6e1b51005c3258fce06c69a9e))

# [1.2.0](https://github.com/byteshard/schema/compare/v1.1.1...v1.2.0) (2023-10-10)


### Features

* add grants to mysql schema ([207b48a](https://github.com/byteshard/schema/commit/207b48ae42933103a77ea4c14f5f40675e397fb5))
* add return type documentation and add getGrants to interface ([7e12257](https://github.com/byteshard/schema/commit/7e1225774a376a7ba156b2c4e49b51ee91e26c8c))

## [1.1.1](https://github.com/byteshard/schema/compare/v1.1.0...v1.1.1) (2023-10-10)


### Bug Fixes

* add separator to columns in indices ([8453550](https://github.com/byteshard/schema/commit/84535500b3520a0d301f8bb26af3a28937d395c0))

# [1.1.0](https://github.com/byteshard/schema/compare/v1.0.1...v1.1.0) (2023-10-10)


### Features

* add indices to schema generation ([de6fadf](https://github.com/byteshard/schema/commit/de6fadfd1b6e2170e0185d89aa951dced4360caa))

## [1.0.1](https://github.com/byteshard/schema/compare/v1.0.0...v1.0.1) (2023-10-09)


### Bug Fixes

* length can be a string for decimal precision ([1f675db](https://github.com/byteshard/schema/commit/1f675dbe148b061eb282908ec8eb8884a3cb0bc9))

# 1.0.0 (2023-05-10)


### Features

* initial commit for schema ([b2289f3](https://github.com/byteshard/schema/commit/b2289f3949956865e8580a4b7b19d56c440882c0))
