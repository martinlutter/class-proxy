# Class Proxy
![Tests](https://github.com/martinlutter/class-proxy/actions/workflows/ci.yml/badge.svg?branch=master)

This package lets you easily cache results in memory from your injected objects without having to write boilerplate code.

It's meant for simple use cases, for methods without big/complex parameters or results. Results are stored in memory, so it will be allocated until the request ends.  
More details [below](#How it works).

## Install
```
composer require martinlutter/class-proxy
```

## Usage
Use Cache attribute in the constructor or a Required method:
```php
use ClassProxy\DependencyInjection\Attribute\Cache;
...

//original injected object will be extended by a proxy class and injected here
public function __construct(#[Cache] private readonly RepositoryInterface $repository)
{
}
```
```php
use ClassProxy\DependencyInjection\Attribute\Cache;
use Symfony\Contracts\Service\Attribute\Required;

#[Required]
public function setDependencies(#[Cache] private readonly RepositoryInterface $repository): void
{
    $this->repository = $repository;
}

```
Then use your injected objects the same as before:
```php
public function __invoke(): Response
{
    //first call will run the original method - will query database
    $result = $this->repository->getAll();
    ...
    //any subsequent call will return the cached data 
    $cachedResult = $this->repository->getAll();
    ...
    //database hit
    $userOne = $this->repository->getUserById(1);
    //cache hit
    $userOneAgain = $this->repository->getUserById(1);
    //database hit
    $userTwo = $this->repository->getUserById(2);
    ...
}
```

## How it works
The package provides the Cache attribute used with dependency injected classes and extends them with a generated proxy class, overriding public methods and caching results from them.

The caching is done in memory and the results are stored in an array indexed by the given method and its hashed parameters.  
The generated classes are dumped into the cache folder, autoloaded, and registered in the DI container.  
Then they are injected **only** into places marked by the Cache attribute (the original service still exists) and the service is shared. So the result can be cached in one place, then accessible from elsewhere.

## Development
Using https://github.com/dunglas/symfony-docker

1. If not already done, [install Docker Compose](https://docs.docker.com/compose/install/) (v2.10+)
2. Run `docker compose build --pull --no-cache` to build fresh images
3. Run `docker compose up -d` 
4. Run `composer install`

Run `docker compose down --remove-orphans` to stop the Docker containers.

## Tests
```
./vendor/bin/codecept run
```
## Code style
```
./vendor/bin/psalm
```
