import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\FailedJobClearAllController::destroy
* @see src/Http/Controllers/FailedJobClearAllController.php:13
* @route '/horizon/failed'
*/
export const destroy = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/horizon/failed',
} satisfies RouteDefinition<["delete"]>

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\FailedJobClearAllController::destroy
* @see src/Http/Controllers/FailedJobClearAllController.php:13
* @route '/horizon/failed'
*/
destroy.url = (options?: RouteQueryOptions) => {
    return destroy.definition.url + queryParams(options)
}

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\FailedJobClearAllController::destroy
* @see src/Http/Controllers/FailedJobClearAllController.php:13
* @route '/horizon/failed'
*/
destroy.delete = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(options),
    method: 'delete',
})

const clearAll = {
    destroy: Object.assign(destroy, destroy),
}

export default clearAll