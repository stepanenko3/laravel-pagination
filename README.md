# Laravel Pagination

[![Latest Version on Packagist](https://img.shields.io/packagist/v/stepanenko3/laravel-pagination.svg?style=flat-square)](https://packagist.org/packages/stepanenko3/laravel-pagination)
[![Total Downloads](https://img.shields.io/packagist/dt/stepanenko3/laravel-pagination.svg?style=flat-square)](https://packagist.org/packages/stepanenko3/laravel-pagination)
[![License](https://poser.pugx.org/stepanenko3/laravel-pagination/license)](https://packagist.org/packages/stepanenko3/laravel-pagination)

## Description

Great pagination generator for Laravel

## Examples

1, 2, 3, 4, 5, ..., 20
1, ..., 12, 13, 14, ..., 20
1, ..., 16, 17, 18, 19, 20

## Requirements

- `php: >=8.0`
- `laravel/framework: ^9.0`

## Installation

```bash
# Install the package
composer require stepanenko3/laravel-pagination
```

## Usage

Create your own database builder in `app\Builders\BaseBuilder.php`

``` php
use Stepanenko3\LaravelPagination\Pagination;
use Illuminate\Database\Eloquent\Builder;

class BaseBuilder extends Builder
{
    public function paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null)
    {
        $page = $page ?: Pagination::resolveCurrentPage($pageName);
        $perPage = $perPage ?: $this->model->getPerPage();
        $results = ($total = $this->toBase()->getCountForPagination())
            ? $this->forPage($page, $perPage)->get($columns)
            : $this->model->newCollection();

        return new Pagination($results, $total, $perPage, $page, [
            'path' => Pagination::resolveCurrentPath(),
            'pageName' => $pageName,
        ]);
    }
}
```

Or use without database builder

``` php
new Pagination(
    $items,
    $total,
    $perPage,
    $currentPage,
);
```

## Credits

- [Artem Stepanenko](https://github.com/stepanenko3)

## Contributing

Thank you for considering contributing to this package! Please create a pull request with your contributions with detailed explanation of the changes you are proposing.

## License

This package is open-sourced software licensed under the [MIT license](LICENSE.md).
