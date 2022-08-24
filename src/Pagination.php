<?php

namespace Stepanenko3\LaravelPagination;

use ArrayAccess;
use Countable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Collection;
use IteratorAggregate;
use JsonSerializable;

class Pagination extends AbstractPaginator implements Arrayable, ArrayAccess, Countable, IteratorAggregate, Jsonable, JsonSerializable, LengthAwarePaginator
{
    /**
     * The total number of items before slicing.
     *
     * @var int
     */
    protected int $total;

    /**
     * The last available page.
     *
     * @var int
     */
    protected int $lastPage;

    protected bool $infinite = false;

    /**
     * Create a new paginator instance.
     *
     * @param  mixed  $items
     * @param  int  $total
     * @param  int  $perPage
     * @param  int|null  $currentPage
     * @param  array  $options  (path, query, fragment, pageName)
     * @return void
     */
    public function __construct($items, $total, $perPage, $currentPage = null, array $options = [])
    {
        $this->options = $options;

        foreach ($options as $key => $value) {
            $this->{$key} = $value;
        }

        $this->total = $total;
        $this->perPage = (int) $perPage;
        $this->lastPage = max((int) ceil($total / $perPage), 1);
        $this->path = $this->path !== '/' ? rtrim($this->path, '/') : $this->path;
        $this->currentPage = $this->setCurrentPage($currentPage, $this->pageName);
        $this->items = $items instanceof Collection ? $items : Collection::make($items);
    }

    /**
     * Get the current page for the request.
     *
     * @param  int  $currentPage
     * @param  string  $pageName
     * @return int
     */
    protected function setCurrentPage($currentPage, $pageName)
    {
        $currentPage = $currentPage ?: static::resolveCurrentPage($pageName);

        return $this->getValidPageNumber($currentPage);
    }

    protected function getValidPageNumber($page)
    {
        if (filter_var($page, FILTER_VALIDATE_INT) !== false) {
            if ($page < 1)
                return 1;

            if ($page > $this->lastPage)
                return $this->lastPage;

            return $page;
        }

        return 1;
    }

    /**
     * Render the paginator using the given view.
     *
     * @param  string|null  $view
     * @param  array  $data
     * @return \Illuminate\Contracts\Support\Htmlable
     */
    public function links($view = null, $data = [])
    {
        return $this->render($view, $data);
    }

    /**
     * Render the paginator using the given view.
     *
     * @param  string|null  $view
     * @param  array  $data
     * @return \Illuminate\Contracts\Support\Htmlable
     */
    public function render($view = null, $data = [])
    {
        return static::viewFactory()->make($view ?: static::$defaultView, array_merge($data, [
            'paginator' => $this,
            'elements' => $this->elements(),
        ]));
    }

    /**
     * Get the paginator links as a collection (for JSON responses).
     *
     * @return \Illuminate\Support\Collection
     */
    public function linkCollection()
    {
        return collect($this->elements())->flatMap(function ($item) {
            if (!is_array($item)) {
                return [['url' => null, 'label' => '...', 'active' => false]];
            }

            return collect($item)->map(function ($url, $page) {
                return [
                    'url' => $url,
                    'label' => (string) $page,
                    'active' => $this->currentPage() === $page,
                ];
            });
        })->prepend([
            'url' => $this->previousPageUrl(),
            'label' => function_exists('__') ? __('pagination.previous') : 'Previous',
            'active' => false,
        ])->push([
            'url' => $this->nextPageUrl(),
            'label' => function_exists('__') ? __('pagination.next') : 'Next',
            'active' => false,
        ]);
    }

    /**
     * Get the array of elements to pass to the view.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function elements(): Collection
    {
        return collect($this->data())
            ->map(fn ($page) => [
                'page' => $page,
                'url' => $page === '...' ? '#' : $this->url($page),
                'active' => $page === $this->currentPage,
            ]);
    }

    /**
     * Get the array of elements to pass to the view.
     *
     * @return array
     */
    public function data(): array
    {
        $delta = 0;

        if ($this->lastPage <= 7) {
            $delta = 7;
        } else {
            $delta = $this->currentPage > 4 && $this->currentPage < $this->lastPage - 3 ? 2 : 4;
        }

        $range = (object) [
            'start' => (int) round($this->currentPage - $delta / 2),
            'end' => (int) round($this->currentPage + $delta / 2),
        ];

        if ($range->start - 1 === 1 || $range->end + 1 === $this->lastPage) {
            $range->start += 1;
            $range->end += 1;
        }

        $pages = $this->currentPage > $delta
            ? range(min([$range->start, $this->lastPage - $delta]), min([$range->end, $this->lastPage]))
            : range(1, min([$this->lastPage, $delta + 1]));

        if ($pages[0] !== 1) {
            $pages = array_merge($this->withDots($pages, 1, [1, '...']), $pages);
        }

        if ($pages[count($pages) - 1] < $this->lastPage) {
            $pages = array_merge($pages, $this->withDots($pages, $this->lastPage, ['...', $this->lastPage]));
        }

        return $pages;


        // $startPage = 1;
        // $endPage = $this->lastPage;

        // if ($this->lastPage >= $this->maxPages) {
        //     // total pages more than max so calculate start and end pages
        //     $maxPagesBeforeCurrentPage = (int) floor($this->maxPages / 2);
        //     $maxPagesAfterCurrentPage = (int) ceil($this->maxPages / 2) - 1;

        //     if ($this->currentPage <= $maxPagesBeforeCurrentPage) {
        //         // current page near the start
        //         $startPage = 1;
        //         $endPage = $this->maxPages;
        //     } elseif ($this->currentPage + $maxPagesAfterCurrentPage >= $this->lastPage) {
        //         // current page near the end
        //         $startPage = $this->lastPage - $this->maxPages + 1;
        //         $endPage = $this->lastPage;
        //     } else {
        //         $startPage = $this->currentPage - $maxPagesBeforeCurrentPage;
        //         $endPage = $this->currentPage + $maxPagesAfterCurrentPage;
        //     }
        // }

        // // create an array of pages to ng-repeat in the pager control
        // $pages = range($startPage, $endPage);

        // // calculate start pages
        // $beforePages = [];
        // if ($pages[0] > $this->onEachSide + 2) {
        //     $beforePages = [
        //         range(1, $this->onEachSide),
        //         '...',
        //     ];
        // } elseif ($startPage - 1 > 0) {
        //     $beforePages = [
        //         range(1, $startPage - 1),
        //     ];
        // }

        // // calculate end pages
        // $afterPages = [];
        // if (max($pages) < $this->lastPage - ($this->onEachSide + 2) + 1) {
        //     $afterPages = [
        //         '...',
        //         range($this->lastPage - $this->onEachSide + 1, $this->lastPage),
        //     ];
        // } elseif (max($pages) < $this->lastPage) {
        //     $afterPages = [
        //         range($endPage + 1, $this->lastPage),
        //     ];
        // }

        // return array_merge($beforePages, [$pages], $afterPages);
    }


    private function withDots($pages, $value, $pair) {
        return count($pages) + 1 !== $this->lastPage ? $pair : [$value];
    }

    /**
     * Get the total number of items being paginated.
     *
     * @return int
     */
    public function total(): int
    {
        return $this->total;
    }

    /**
     * Determine if there are more items in the data source.
     *
     * @return bool
     */
    public function hasMorePages(): bool
    {
        return $this->currentPage() < $this->lastPage();
    }

    /**
     * Get the last page.
     *
     * @return int
     */
    public function lastPage(): int
    {
        return $this->lastPage;
    }

    public function lastPageUrl(): string
    {
        return $this->url($this->lastPage());
    }

    public function firstPageUrl(): string
    {
        return $this->url(1);
    }

    public function progress(): int
    {
        if (($this->lastPage - 1) <= 0) {
            return 100;
        }

        return ($this->currentPage - 1) / ($this->lastPage - 1) * 100;
    }

    public function start(): int
    {
        return $this->currentPage <= 1 ? 1 : ($this->currentPage * $this->perPage) - $this->perPage + 1;
    }

    public function end(): int
    {
        $end = $this->currentPage <= 1 ? $this->perPage : ($this->currentPage * $this->perPage);

        if ($end > $this->total) $end = $this->total;

        return $end;
    }

    public function prevPageUrl(): string
    {
        return $this->url($this->prevPage());
    }

    public function nextPageUrl(): string
    {
        return $this->url($this->nextPage());
    }

    public function prevPage(): int
    {
        if ($this->onFirstPage()) {
            if ($this->infinite) {
                return $this->lastPage;
            }

            return 1;
        }

        return $this->currentPage - 1;
    }

    public function nextPage(): int
    {
        if ($this->hasMorePages()) {
            return $this->currentPage + 1;
        }

        if ($this->infinite) {
            return 1;
        }

        return $this->lastPage;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'currentPage' => $this->currentPage(),
            'data' => $this->items->toArray(),
            'firstPageUrl' => $this->firstPageUrl(),
            'from' => $this->firstItem(),
            'lastPage' => $this->lastPage(),
            'lastPageUrl' => $this->lastPageUrl(),
            'perPage' => $this->perPage(),
            'total' => $this->total(),
            'start' => $this->start(),
            'end' => $this->end(),
            'progress' => $this->progress(),
            'elements' => $this->elements(),
            'onFirstPage' => $this->onFirstPage(),
            'hasMorePages' => $this->hasMorePages(),
            'hasPages' => $this->hasPages(),
            'prevPage' => $this->prevPage(),
            'nextPage' => $this->nextPage(),
            'prevPageUrl' => $this->prevPageUrl(),
            'nextPageUrl' => $this->nextPageUrl(),
            'path' => $this->path(),
        ];
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->jsonSerialize(), $options);
    }
}
