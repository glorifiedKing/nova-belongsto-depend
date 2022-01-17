<?php

namespace Orlyapps\NovaBelongsToDepend;

use Illuminate\Support\Str;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\FormatsRelatableDisplayValues;
use Laravel\Nova\Fields\ResourceRelationshipGuesser;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Nova;

class NovaBelongsToDepend extends BelongsTo
{
    use FormatsRelatableDisplayValues;

    public $resourceParentClass;

    public $modelClass;
    public $modelPrimaryKey;
    public $foreignKeyName;

    public $valueKey;

    public $titleKey;

    public $dependsOn = [];

    public $dependsMap = [];

    public $optionResolveCallback = null;
    public $options = [];

    public $fallback;

    public $showLinkToResourceFromDetail = true;
    public $showLinkToResourceFromIndex = true;
    public $ignoreRelation = false;

    /**
     * The field's component.
     *
     * @var string
     */
    public $component = 'nova-belongsto-depend';

    public function __construct($name, $attribute = null, $resource = null)
    {
        $resource = $resource ?? ResourceRelationshipGuesser::guessResource($name);
        parent::__construct($name, $attribute, $resource);

        $this->modelClass = get_class($resource::newModel());
        $this->modelPrimaryKey = $resource::newModel()->getKeyName();
        $this->titleKey = $resource::$title;
        $this->optionResolveCallback = function () {
            return [];
        };
    }

    public function placeholder($placeholder)
    {
        $this->withMeta(['placeholder' => $placeholder]);
        return $this;
    }

    public function openDirection(string $openDirection)
    {
        $this->withMeta(['openDirection' => $openDirection]);
        return $this;
    }

    public function options($options)
    {
        $this->options = collect($options);
        return $this;
    }

    public function optionsResolve($callback)
    {
        $this->optionResolveCallback = $callback;
        return $this;
    }

    public function dependsOn(string ...$classNames): NovaBelongsToDepend
    {
        foreach ($classNames as &$value) {
            $value = Str::lower($value);
        }
        $this->dependsOn = $classNames;
        return $this;
    }

    public function fallback($fallback)
    {
        $this->fallback = $fallback;
        return $this;
    }

    /**
     * @param $parentResourceClass
     * @return self
     */
    public function setResourceParentClass($parentResourceClass)
    {
        $this->resourceParentClass = $parentResourceClass;
        return $this;
    }

    public function hideLinkToResourceFromDetail()
    {
        $this->showLinkToResourceFromDetail = false;
        return $this;
    }
    public function ignoreRelation()
    {
        $this->ignoreRelation = true;
        return $this;
    }

    public function hideLinkToResourceFromIndex()
    {
        $this->showLinkToResourceFromIndex = false;
        return $this;
    }

    public function resolve($resource, $attribute = null)
    {
        $testInstance = new \ReflectionClass($resource);
        if ($testInstance->isAnonymous()) {
            return $this;
        }

        if ($resource instanceof Action) {
            $this->resourceParentClass = get_class($resource);
            return $this;
        }

        if($this->ignoreRelation){
            // parent::resolveAttribute($resource,$attribute);
            parent::resolve($resource, $attribute);
            $this->resourceParentClass = get_class(Nova::newResourceFromModel($resource));
 
             $foreign = $resource->{$this->attribute}();
             $this->foreignKeyName = $this->attribute;//$foreign->getForeignKeyName();
 
             $value = $resource->{$this->attribute}()->withoutGlobalScopes()->first();
             if ($value) {
                 $this->valueKey = $value->getKey();
                 $this->value = $this->formatDisplayValue($value);
             }
 
             //parent::resolveAttribute($resource,$attribute);
             $this->value = data_get($resource, str_replace('->', '.', $attribute));
             $this->valueKey = $resource->{$this->attribute};
         }
 
         else{
             parent::resolve($resource, $attribute);
             $this->resourceParentClass = get_class(Nova::newResourceFromModel($resource));
 
             $foreign = $resource->{$this->attribute}();
             $this->foreignKeyName = $foreign->getForeignKeyName();
 
             $value = $resource->{$this->attribute}()->withoutGlobalScopes()->first();
             if ($value) {
                 $this->valueKey = $value->getKey();
                 $this->value = $this->formatDisplayValue($value);
             }
 
             if ($this->fallback) {
                 $this->fallback->resolve($resource);
             }
         }
    }

    /**
     * @param mixed $resource
     * @param null $attribute
     */
    public function resolveForDisplay($resource, $attribute = null)
    {
        parent::resolveForDisplay($resource, $attribute);

        if ($resource->{$this->foreignKeyName} === null && $this->fallback) {
            $this->value = $resource->{$this->fallback->attribute};
            return;
        }
        if($this->ignoreRelation){
            $this->value = $resource->{$this->attribute};
            return;
        }

        $this->fallback = false;
    }

    /**
     * Hydrate the given attribute on the model based on the incoming request.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @param object $model
     * @return mixed
     */
    public function fillForAction(NovaRequest $request, $model)
    {
        if (isset($request[$this->attribute])) {
            $model->{$this->attribute} = $request[$this->attribute];
        }
        return $model->{$this->attribute};
    }

    /**
     * Fills the attributes of the model within the container if the dependencies for the container are satisfied.
     *
     * @param NovaRequest $request
     * @param string $requestAttribute
     * @param object $model
     * @param string $attribute
     */
    protected function fillAttributeFromRequest(NovaRequest $request, $requestAttribute, $model, $attribute)
    {
        if ($request->exists($requestAttribute)) {
            $model->{$attribute} = $request[$requestAttribute];
        }

        if ($this->fallback) {
            $this->fallback->fill($request, $model);
        }
    }

    /**
     * Return the sortable uri key for the field.
     *
     * @return string
     */
    public function sortableUriKey()
    {
        $request = app(NovaRequest::class);
        return $this->getRelationForeignKeyName($request->newResource()->resource->{$this->attribute}());
    }

    public function meta()
    {
        $this->meta = parent::meta();
        return array_merge([
            'options' => $this->options,
            'valueKey' => $this->valueKey,
            'dependsMap' => $this->dependsMap,
            'dependsOn' => $this->dependsOn,
            'titleKey' => $this->titleKey,
            'resourceParentClass' => $this->resourceParentClass,
            'modelClass' => $this->modelClass,
            'modelPrimaryKey' => $this->modelPrimaryKey,
            'foreignKeyName' => $this->foreignKeyName,
            'fallback' => $this->fallback,
            'showLinkToResourceFromDetail' => $this->showLinkToResourceFromDetail,
            'showLinkToResourceFromIndex' => $this->showLinkToResourceFromIndex,
            'ignoreRelation' => $this->ignoreRelation,
        ], $this->meta);
    }

    /**
     * Get the validation rules for this field.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function getRules(NovaRequest $request)
    {
        $query = $this->buildAssociatableQuery(
            $request, $request->{$this->attribute.'_trashed'} === 'true'
        )->toBase();
        if($this->ignoreRelation){
            return array_merge_recursive([$this->attribute => is_callable($this->rules)
            ? call_user_func($this->rules, $request)
            : $this->rules, ], [
                $this->attribute => array_filter([
                    $this->nullable ? 'nullable' : 'required',
                    
                ]),
            ]); 
            ;
        } 
        else {
            return array_merge_recursive(parent::getRules($request), [
                
            ]);
        }   
        
    }

     /**
     * Hydrate the given attribute on the model based on the incoming request.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  object  $model
     * @return void
     */
    public function fill(NovaRequest $request, $model)
    {
        $foreignKey = ($this->ignoreRelation) ? $this->attribute : $this->getRelationForeignKeyName($model->{$this->attribute}());

        parent::fillInto($request, $model, $foreignKey);

        if ($model->isDirty($foreignKey)) {
            $model->unsetRelation($this->attribute);
        }

        if ($this->filledCallback) {
            call_user_func($this->filledCallback, $request, $model);
        }
    }
}
