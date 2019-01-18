<?php namespace Captive\CalendarWidget\Behaviors;

use Db;
use Str;
use Lang;
use Flash;
use Event;
use Redirect;
use Backend;
use Backend\Classes\ControllerBehavior;
use October\Rain\Html\Helper as HtmlHelper;
use October\Rain\Router\Helper as RouterHelper;
use ApplicationException;
use Exception;

class CalendarController extends ControllerBehavior
{
    /**
     * @var string Default context for "create" pages.
     */
    const CONTEXT_CREATE = 'create';

    /**
     * @var string Default context for "update" pages.
     */
    const CONTEXT_UPDATE = 'update';

    /**
     * @var string Default context for "preview" pages.
     */
    const CONTEXT_PREVIEW = 'preview';

    /**
     * @var string The context to pass to the form widget.
     */
    protected $context;


    /**
     * @var \Backend\Classes\WidgetBase Reference to the toolbar widget objects.
     */
    protected $toolbarWidget = null;

    /**
     * @var \Backend\Classes\WidgetBase Reference to the filter widget objects.
     */
    protected $filterWidget;

    protected $calendarWidget;

    /**
     * @var Model The initialized model used by the form.
     */
    protected $model;


    /**
     * @var array Configuration values that must exist when applying the primary config file.
     * - modelClass: Class name for the model
     * - list: list field definitions
     */
    protected $requiredConfig = ['modelClass', 'list'];

    /**
     * Behavior constructor
     * @param \Backend\Classes\Controller $controller
     */
    public function __construct($controller)
    {
        parent::__construct($controller);


        /*
         * Build configuration
         */
        $this->config = $this->makeConfig($controller->calendarConfig, $this->requiredConfig);
        $this->config->modelClass = Str::normalizeClassName($this->config->modelClass);
    }

    /**
     * Index Controller action.
     * @return void
     */
    public function index()
    {
        $useList = get('list');
        if ($useList) {
            $this->controller->asExtension('Backend\Behaviors\ListController')->index();
            return ;
        }

        $this->controller->pageTitle = $this->controller->pageTitle ? : Lang::get($this->getConfig(
            'title',
            'Calendar'
        ));
        $this->controller->bodyClass = 'slim-container';
        $this->makeCalendar();
    }

    public function makeCalendar($context = null)
    {
        if ($context !== null) {
            $this->context = $context;
        }
        $model = $this->controller->calendarCreateModelObject();

        $config = $this->config;
        $config->model = $model;
        $config->alias = 'calendar';
        $widget = $this->makeWidget('\Captive\CalendarWidget\Widgets\Calendar', $config);
        $widget->bindToController();
        $this->calendarWidget = $widget;

        if (isset($config->toolbar)) {
            $toolbarConfig = $this->makeConfig($config->toolbar);
            $toolbarConfig->alias = $widget->alias . 'Toolbar';
            $toolbarWidget = $this->makeWidget('Backend\Widgets\Toolbar', $toolbarConfig);
            $toolbarWidget->bindToController();
            $toolbarWidget->cssClasses[] = 'list-header';
            /*
             * Link the Search Widget to the List Widget
             */
            if ($searchWidget = $toolbarWidget->getSearchWidget()) {
                $searchWidget->bindEvent('search.submit', function () use ($widget, $searchWidget) {
                    $widget->setSearchTerm($searchWidget->getActiveTerm());
                    return $widget->onRefresh();
                });

                $widget->setSearchOptions([
                    'mode' => $searchWidget->mode,
                    'scope' => $searchWidget->scope,
                ]);

                // Find predefined search term
                $widget->setSearchTerm($searchWidget->getActiveTerm());
            }
            $this->toolbarWidget = $toolbarWidget;
        }
        return $widget;
    }

    /**
     * Internal method used to prepare the form model object.
     *
     * @return October\Rain\Database\Model
     */
    protected function createModel()
    {
        $class = $this->config->modelClass;
        return new $class;
    }
    /**
     * Creates a new instance of a form model. This logic can be changed
     * by overriding it in the controller.
     * @return Model
     */
    public function calendarCreateModelObject()
    {
        return $this->createModel();
    }

    public function calendarRender($options = [])
    {
        $useList = get('list');
        if ($useList) {
            return $this->controller->asExtension('Backend\Behaviors\ListController')->listRender();
        }

        if (empty($this->calendarWidget)) {
            throw new ApplicationException(Lang::get('backend::lang.list.behavior_not_ready'));
        }

        $vars = [
            'toolbar' => $this->toolbarWidget,
            'filter' => null,
            'calendar' => $this->calendarWidget,
        ];



        // return $this->calendarWidget->render($options);
        return $this->calendarMakePartial('container', $vars);
    }

    public function calendarMakepartial($partial, $params = [])
    {
        $contents = $this->controller->makePartial('calendar_' . $partial, $params, false);
        if (! $contents) {
            $contents = $this->makePartial($partial, $params);
        }
        return $contents;
    }
}
