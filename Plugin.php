<?php namespace Winter\Pages;

use Backend;
use Backend\Models\UserRole;
use Cms\Classes\Controller as CmsController;
use Cms\Classes\Theme;
use Config;
use Event;
use System\Classes\PluginBase;
use Winter\Pages\Classes\Controller;
use Winter\Pages\Classes\Page as StaticPage;
use Winter\Pages\Classes\Router;
use Winter\Pages\Classes\Snippet;
use Winter\Pages\Classes\SnippetManager;
use Winter\Pages\Controllers\Index;

class Plugin extends PluginBase
{
    public function pluginDetails()
    {
        return [
            'name'        => 'winter.pages::lang.plugin.name',
            'description' => 'winter.pages::lang.plugin.description',
            'author'      => 'Winter CMS',
            'icon'        => 'icon-files-o',
            'homepage'    => 'https://github.com/wintercms/wn-pages-plugin',
            'replaces'    => ['RainLab.Pages' => '<= 1.4.10'],
        ];
    }

    /**
     * Register the CMS components provided by this plugin
     */
    public function registerComponents(): array
    {
        return [
            \Winter\Pages\Components\ChildPages::class => 'childPages',
            \Winter\Pages\Components\StaticPage::class => 'staticPage',
            \Winter\Pages\Components\StaticMenu::class => 'staticMenu',
            \Winter\Pages\Components\StaticBreadcrumbs::class => 'staticBreadcrumbs'
        ];
    }

    /**
     * Register the permissions provided by this plugin
     */
    public function registerPermissions(): array
    {
        return [
            'winter.pages.manage_pages' => [
                'tab'   => 'winter.pages::lang.page.tab',
                'order' => 200,
                'roles' => [UserRole::CODE_DEVELOPER, UserRole::CODE_PUBLISHER],
                'label' => 'winter.pages::lang.page.manage_pages',
            ],
            'winter.pages.manage_menus' => [
                'tab'   => 'winter.pages::lang.page.tab',
                'order' => 200,
                'roles' => [UserRole::CODE_DEVELOPER, UserRole::CODE_PUBLISHER],
                'label' => 'winter.pages::lang.page.manage_menus',
                ],
            'winter.pages.manage_content' => [
                'tab'   => 'winter.pages::lang.page.tab',
                'order' => 200,
                'roles' => [UserRole::CODE_DEVELOPER, UserRole::CODE_PUBLISHER],
                'label' => 'winter.pages::lang.page.manage_content',
            ],
            'winter.pages.access_snippets' => [
                'tab'   => 'winter.pages::lang.page.tab',
                'order' => 200,
                'roles' => [UserRole::CODE_DEVELOPER, UserRole::CODE_PUBLISHER],
                'label' => 'winter.pages::lang.page.access_snippets',
            ],
            'winter.pages.access_preview' => [
                'tab'   => 'winter.pages::lang.page.tab',
                'order' => 200,
                'roles' => [UserRole::CODE_DEVELOPER, UserRole::CODE_PUBLISHER],
                'label' => 'winter.pages::lang.page.access_preview',
            ],
        ];
    }

    /**
     * Register the backend navigation items provided by this plugin
     */
    public function registerNavigation(): array
    {
        return [
            'pages' => [
                'label'       => 'winter.pages::lang.plugin.name',
                'url'         => Backend::url('winter/pages'),
                'icon'        => 'icon-files-o',
                'iconSvg'     => 'plugins/winter/pages/assets/images/pages-icon.svg',
                'permissions' => ['winter.pages.*'],
                'order'       => 200,

                'sideMenu' => [
                    'pages' => [
                        'label'       => 'winter.pages::lang.page.menu_label',
                        'icon'        => 'icon-files-o',
                        'url'         => 'javascript:;',
                        'attributes'  => ['data-menu-item'=>'pages'],
                        'permissions' => ['winter.pages.manage_pages'],
                    ],
                    'menus' => [
                        'label'       => 'winter.pages::lang.menu.menu_label',
                        'icon'        => 'icon-sitemap',
                        'url'         => 'javascript:;',
                        'attributes'  => ['data-menu-item'=>'menus'],
                        'permissions' => ['winter.pages.manage_menus'],
                    ],
                    'content' => [
                        'label'       => 'winter.pages::lang.content.menu_label',
                        'icon'        => 'icon-file-text-o',
                        'url'         => 'javascript:;',
                        'attributes'  => ['data-menu-item'=>'content'],
                        'permissions' => ['winter.pages.manage_content'],
                    ],
                    'snippets' => [
                        'label'       => 'winter.pages::lang.snippet.menu_label',
                        'icon'        => 'icon-newspaper-o',
                        'url'         => 'javascript:;',
                        'attributes'  => ['data-menu-item'=>'snippet'],
                        'permissions' => ['winter.pages.access_snippets'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Register the backend FormWidgets provided by this plugin
     */
    public function registerFormWidgets(): array
    {
        return [
            FormWidgets\PagePicker::class => 'staticpagepicker',
            FormWidgets\MenuPicker::class => 'staticmenupicker',
        ];
    }

    /**
     * Register Twig extensions provided by this plugin
     */
    public function registerMarkupTags(): array
    {
        return [
            'filters' => [
                'staticPage' => ['Winter\Pages\Classes\Page', 'url']
            ]
        ];
    }

    /**
     * Clear the caches used by this plugin
     */
    public static function clearCache(): void
    {
        $theme = Theme::getEditTheme();

        $router = new Router($theme);
        $router->clearCache();

        StaticPage::clearMenuCache($theme);
        SnippetManager::clearCache($theme);
    }

    /**
     * Boot the plugin
     */
    public function boot(): void
    {
        $this->extendThemeSync();
        $this->extendBackendForms();
        $this->extendCmsTemplates();
        $this->extendCmsRouter();
        $this->extendCmsRenderer();

        $this->registerMenuItemTypes();
        $this->registerRichEditorLinkTypes();
    }

    /**
     * Extend the theme:sync command to include Pages & Menus in the sync
     */
    protected function extendThemeSync(): void
    {
        Event::listen('system.console.theme.sync.getAvailableModelClasses', function () {
            return [
                Classes\Menu::class,
                Classes\Page::class,
            ];
        });
    }

    /**
     * Extend the backend forms
     */
    protected function extendBackendForms(): void
    {
        Event::listen('backend.form.extendFieldsBefore', function ($formWidget) {
            if ($formWidget->model instanceof \Cms\Classes\Partial) {
                Snippet::extendPartialForm($formWidget);
            }
        });

        // Add the Preview tab as the last tab with a event priority that still
        // allows other plugins to change it if desired.
        Event::listen('backend.form.extendFieldsBefore', function ($formWidget) {
            if (
                $formWidget->isNested
                || !($formWidget->getController() instanceof Index)
                || !($formWidget->model instanceof StaticPage)
            ) {
                return;
            }

            $existingFields = array_merge(
                array_keys($formWidget->fields ?? []),
                array_keys($formWidget->tabs['fields'] ?? []),
                array_keys($formWidget->secondaryTabs['fields'] ?? [])
            );

            $formWidget->secondaryTabs['fields'] = array_merge($formWidget->secondaryTabs['fields'], [
                '_preview' => [
                    'tab' => 'winter.pages::lang.editor.preview',
                    'type' => 'partial',
                    'span' => 'full',
                    'path' => '$/winter/pages/classes/page/field.preview.php',
                    'permissions' => ['winter.pages.access_preview'],
                    'dependsOn' => $existingFields,
                ],
            ]);
        }, PHP_INT_MIN + 1);
    }

    /**
     * Extend CMS templates
     */
    protected function extendCmsTemplates(): void
    {
        Event::listen('cms.template.save', function($controller, $template, $type) {
            Plugin::clearCache();
        });

        Event::listen('cms.template.processSettingsBeforeSave', function($controller, $dataHolder) {
            $dataHolder->settings = Snippet::processTemplateSettingsArray($dataHolder->settings);
        });

        Event::listen('cms.template.processSettingsAfterLoad', function($controller, $template) {
            Snippet::processTemplateSettings($template);
        });

        Event::listen('cms.template.processTwigContent', function($template, $dataHolder) {
            if ($template instanceof \Cms\Classes\Layout) {
                $dataHolder->content = Controller::instance()->parseSyntaxFields($dataHolder->content);
            }
        });
    }

    /**
     * Extend the CMS router
     */
    protected function extendCmsRouter(): void
    {
        Event::listen('cms.router.beforeRoute', function($url) {
            return Controller::instance()->initCmsPage($url);
        });
    }

    /**
     * Extend the CMS renderer
     */
    protected function extendCmsRenderer(): void
    {
        Event::listen('cms.page.beforeRenderPage', function($controller, $page) {
            /*
             * Before twig renders
             */
            $twig = $controller->getTwig();
            $loader = $controller->getLoader();
            Controller::instance()->injectPageTwig($page, $loader, $twig);

            /*
             * Get rendered content
             */
            $contents = Controller::instance()->getPageContents($page);
            if (strlen($contents)) {
                return $contents;
            }
        });

        Event::listen('cms.page.initComponents', function($controller, $page) {
            Controller::instance()->initPageComponents($controller, $page);
        });

        Event::listen('cms.block.render', function($blockName, $blockContents) {
            $page = CmsController::getController()->getPage();

            if (!isset($page->apiBag['staticPage'])) {
                return;
            }

            $contents = Controller::instance()->getPlaceholderContents($page, $blockName, $blockContents);
            if (strlen($contents)) {
                return $contents;
            }
        });
    }

    /**
     * Register the frontend Menu Item types provided by this plugin
     */
    protected function registerMenuItemTypes(): void
    {
        Event::listen('pages.menuitem.listTypes', function() {
            return [
                'static-page'      => 'winter.pages::lang.menuitem.static_page',
                'all-static-pages' => 'winter.pages::lang.menuitem.all_static_pages'
            ];
        });

        Event::listen('pages.menuitem.getTypeInfo', function($type) {
            if ($type == 'url') {
                return [];
            }

            if ($type == 'static-page'|| $type == 'all-static-pages') {
                return StaticPage::getMenuTypeInfo($type);
            }
        });

        Event::listen('pages.menuitem.resolveItem', function($type, $item, $url, $theme) {
            if ($type == 'static-page' || $type == 'all-static-pages') {
                return StaticPage::resolveMenuItem($item, $url, $theme);
            }
        });
    }

    /**
     * Register the link types provided by this plugin to the richeditor
     */
    protected function registerRichEditorLinkTypes(): void
    {
        Event::listen('backend.richeditor.listTypes', function () {
            return [
                'static-page' => 'winter.pages::lang.menuitem.static_page',
            ];
        });

        Event::listen('backend.richeditor.getTypeInfo', function ($type) {
            if ($type === 'static-page') {
                return StaticPage::getRichEditorTypeInfo($type);
            }
        });

        Event::listen('system.console.theme.sync.getAvailableModelClasses', function () {
            return [
                Classes\Menu::class,
                Classes\Page::class,
            ];
        });

        BaseBackendController::extend(function ($controller) {
            $user = BackendAuth::getUser();
            if (!$user || !$user->hasAccess('winter.pages.access_snippets')) {
                return;
            }

            // Add the AJAX handlers required for snippet inspector properties
            // to function on all backend controllers
            $controller->addDynamicMethod('onGetInspectorConfiguration', function() {
                return (new StaticPage)->onGetInspectorConfiguration();
            });
            $controller->addDynamicMethod('onGetSnippetNames', function() {
                return (new StaticPage)->onGetSnippetNames();
            });
            $controller->addDynamicMethod('onInspectableGetOptions', function() {
                return (new StaticPage)->onInspectableGetOptions();
            });

            FroalaFormWidget::extend(function ($widget) {
                // Adds default base CSS/JS for snippets
                $widget->addCss('/plugins/winter/pages/assets/css/pages.css', 'Winter.Pages');
                $widget->addJs('/plugins/winter/pages/assets/js/pages-page.js', 'Winter.Pages');
                $widget->addJs('/plugins/winter/pages/assets/js/pages-snippets.js', 'Winter.Pages');

                // Adds custom assets for the Froala snippet button
                $widget->addJs('/plugins/winter/pages/assets/js/froala-snippets.js', 'Winter.Pages');
            });
        });
    }

    /**
     * Register new Twig variables
     */
    public function registerMarkupTags(): array
    {
        return [
            'filters' => [
                'staticPage'    => ['Winter\Pages\Classes\Page', 'url'],
                'parseSnippets' => ['Winter\Pages\Classes\Snippet', 'parse'],
            ]
        ];
    }

    public function registerClassAliases()
    {
        /**
         * To allow compatibility with plugins that extend the original RainLab.Pages plugin,
         * this will alias those classes to use the new Winter.Pages classes.
         */
        return [
            \Winter\Pages\Plugin::class                       => \RainLab\Pages\Plugin::class,
            \Winter\Pages\Classes\Content::class              => \RainLab\Pages\Classes\Content::class,
            \Winter\Pages\Classes\Controller::class           => \RainLab\Pages\Classes\Controller::class,
            \Winter\Pages\Classes\Menu::class                 => \RainLab\Pages\Classes\Menu::class,
            \Winter\Pages\Classes\Page::class                 => \RainLab\Pages\Classes\Page::class,
            \Winter\Pages\Classes\Router::class               => \RainLab\Pages\Classes\Router::class,
            \Winter\Pages\Classes\Snippet::class              => \RainLab\Pages\Classes\Snippet::class,
            \Winter\Pages\Classes\SnippetManager::class       => \RainLab\Pages\Classes\SnippetManager::class,
            \Winter\Pages\Components\ChildPages::class        => \RainLab\Pages\Components\ChildPages::class,
            \Winter\Pages\Components\StaticBreadcrumbs::class => \RainLab\Pages\Components\StaticBreadcrumbs::class,
            \Winter\Pages\Components\StaticMenu::class        => \RainLab\Pages\Components\StaticMenu::class,
            \Winter\Pages\Components\StaticPage::class        => \RainLab\Pages\Components\StaticPage::class,
            \Winter\Pages\Controllers\Index::class            => \RainLab\Pages\Controllers\Index::class,
            \Winter\Pages\FormWidgets\MenuItems::class        => \RainLab\Pages\FormWidgets\MenuItems::class,
            \Winter\Pages\FormWidgets\MenuItemSearch::class   => \RainLab\Pages\FormWidgets\MenuItemSearch::class,
            \Winter\Pages\FormWidgets\PagePicker::class       => \RainLab\Pages\FormWidgets\PagePicker::class,
            \Winter\Pages\Widgets\MenuList::class             => \RainLab\Pages\Widgets\MenuList::class,
            \Winter\Pages\Widgets\PageList::class             => \RainLab\Pages\Widgets\PageList::class,
            \Winter\Pages\Widgets\SnippetList::class          => \RainLab\Pages\Widgets\SnippetList::class,
        ];
    }

    public static function clearCache()
    {
        $theme = Theme::getEditTheme();

        $router = new Router($theme);
        $router->clearCache();

        StaticPage::clearMenuCache($theme);
        SnippetManager::clearCache($theme);
    }
}
