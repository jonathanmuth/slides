<?php

use Kirby\Cms\App;
use Kirby\Cms\Blueprint;
use Kirby\Toolkit\A;
use Kirby\Toolkit\F;
use Kirby\Toolkit\Str;

return [
    'mixins' => [
        'empty',
        'headline',
        'layout',
        'min',
        'max',
        'pagination',
        'parent'
    ],
    'props' => [
        /**
         * Optional array of templates that should only be allowed to add.
         */
        'create' => function ($add = null) {
            return A::wrap($add);
        },
        /**
         * Image options to control the source and look of page previews
         */
        'image' => function ($image = null) {
            return $image ?? [];
        },
        /**
         * Optional info text setup. Info text is shown on the right (lists) or below (cards) the page title.
         */
        'info' => function (string $info = null) {
            return $info;
        },
        /**
         * The size option controls the size of cards. By default cards are auto-sized and the cards grid will always fill the full width. With a size you can disable auto-sizing. Available sizes: tiny, small, medium, large
         */
        'size' => function (string $size = 'auto') {
            return $size;
        },
        /**
         * Enables/disables manual sorting
         */
        'sortable' => function (bool $sortable = true) {
            return $sortable;
        },
        /**
         * Overwrites manual sorting and sorts by the given field and sorting direction (i.e. date desc)
         */
        'sortBy' => function (string $sortBy = null) {
            return $sortBy;
        },
        /**
         * Filters pages by their status. Available status settings: draft, unlisted, listed, published, all.
         */
        'status' => function (string $status = '') {
            if ($status === 'drafts') {
                $status = 'draft';
            }

            if (in_array($status, ['all', 'draft', 'published', 'listed', 'unlisted']) === false) {
                $status = 'all';
            }

            return $status;
        },
        /**
         * Setup for the main text in the list or cards. By default this will display the page title.
         */
        'text' => function (string $text = '{{ page.title }}') {
            return $text;
        }
    ],
    'computed' => [
        'dragTextType' => function () {
            return option('panel.kirbytext', true) ? 'kirbytext' : 'markdown';
        },
        'templates' => function () {
            return A::wrap($this->templates ?? $this->template);
        },
        'parent' => function () {
            return $this->parentModel();
        },
        'pages' => function () {
            switch ($this->status) {
                case 'draft':
                    $pages = $this->parent->drafts();
                    break;
                case 'listed':
                    $pages = $this->parent->children()->listed();
                    break;
                case 'published':
                    $pages = $this->parent->children();
                    break;
                case 'unlisted':
                    $pages = $this->parent->children()->unlisted();
                    break;
                default:
                    $pages = $this->parent->childrenAndDrafts();
            }

            // loop for the best performance
            foreach ($pages->data as $id => $page) {

                // remove all protected pages
                if ($page->isReadable() === false) {
                    unset($pages->data[$id]);
                    continue;
                }

                // filter by all set templates
                if ($this->templates && in_array($page->intendedTemplate()->name(), $this->templates) === false) {
                    unset($pages->data[$id]);
                    continue;
                }
            }

            // sort
            if ($this->sortBy) {
                $pages = $pages->sortBy(...Str::split($this->sortBy, ' '));
            }

            // pagination
            $pages = $pages->paginate([
                'page'  => $this->page,
                'limit' => $this->limit
            ]);

            return $pages;
        },
        'total' => function () {
            return $this->pages->pagination()->total();
        },
        'data' => function () {
            $data = [];

            if ($this->layout === 'list') {
                $thumb = [
                    'width'  => 100,
                    'height' => 100
                ];
            } else {
                $thumb = [
                    'width'  => 400,
                    'height' => 400
                ];
            }

            foreach ($this->pages as $item) {
                $permissions = $item->permissions();
                $blueprint   = $item->blueprint();
                $image       = $item->panelImage($this->image, $thumb);

                $data[] = [
                    'id'          => $item->id(),
                    'dragText'    => $item->dragText($this->dragTextType),
                    'text'        => $item->toString($this->text),
                    'info'        => $item->toString($this->info ?? false),
                    'parent'      => $item->parentId(),
                    'icon'        => $item->panelIcon($image),
                    'image'       => $image,
                    'link'        => $item->panelUrl(true),
                    'status'      => $item->status(),
                    'permissions' => [
                        'sort'         => $permissions->can('sort'),
                        'changeStatus' => $permissions->can('changeStatus')
                    ]
                ];
            }

            return $data;
        },
        'errors' => function () {
            $errors = [];

            if ($this->validateMax() === false) {
                $errors['max'] = I18n::template('error.section.pages.max.' . I18n::form($this->max), [
                    'max'     => $this->max,
                    'section' => $this->headline
                ]);
            }

            if ($this->validateMin() === false) {
                $errors['min'] = I18n::template('error.section.pages.min.' . I18n::form($this->max), [
                    'min'     => $this->min,
                    'section' => $this->headline
                ]);
            }

            if (empty($errors) === true) {
                return [];
            }

            return [
                $this->name => [
                    'label'   => $this->headline,
                    'message' => $errors,
                ]
            ];
        },
        'add' => function () {
            if (in_array($this->status, ['draft', 'all']) === false) {
                return false;
            }

            if ($this->isFull() === true) {
                return false;
            }

            return true;
        },
        'link' => function () {
            $modelLink  = $this->model->panelUrl(true);
            $parentLink = $this->parent->panelUrl(true);

            if ($modelLink !== $parentLink) {
                return $parentLink;
            }
        },
        'pagination' => function () {
            return $this->pagination();
        },
        'sortable' => function () {
            if ($this->status !== 'listed' && $this->status !== 'all') {
                return false;
            }

            if ($this->sortable === false) {
                return false;
            }

            if ($this->sortBy !== null) {
                return false;
            }

            return true;
        }
    ],
    'methods' => [
        'blueprints' => function () {
            $blueprints = [];
            $templates  = empty($this->create) === false ? $this->create : $this->templates;

            if (empty($templates) === true) {
                foreach (glob(App::instance()->root('blueprints') . '/pages/*.yml') as $blueprint) {
                    $templates[] = F::name($blueprint);
                }
            }

            // convert every template to a usable option array
            // for the template select box
            foreach ($templates as $template) {
                try {
                    $props = Blueprint::load('pages/' . $template);

                    $blueprints[] = [
                        'name'  => basename($props['name']),
                        'title' => $props['title'],
                    ];
                } catch (Throwable $e) {
                    $blueprints[] = [
                        'name'  => basename($template),
                        'title' => ucfirst($template),
                    ];
                }
            }

            return $blueprints;
        }
    ],
    'toArray' => function () {
        return [
            'data'    => $this->data,
            'errors'  => $this->errors,
            'options' => [
                'add'      => $this->add,
                'empty'    => $this->empty,
                'headline' => $this->headline,
                'layout'   => $this->layout,
                'link'     => $this->link,
                'size'     => $this->size,
                'sortable' => $this->sortable
            ],
            'pagination' => $this->pagination,
        ];
    }
];
