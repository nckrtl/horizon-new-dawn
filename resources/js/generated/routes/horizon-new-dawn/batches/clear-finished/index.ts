import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\BatchClearFinishedController::destroy
* @see src/Http/Controllers/BatchClearFinishedController.php:14
* @route '/horizon/batches'
*/
export const destroy = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/horizon/batches',
} satisfies RouteDefinition<["delete"]>

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\BatchClearFinishedController::destroy
* @see src/Http/Controllers/BatchClearFinishedController.php:14
* @route '/horizon/batches'
*/
destroy.url = (options?: RouteQueryOptions) => {
    return destroy.definition.url + queryParams(options)
}

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\BatchClearFinishedController::destroy
* @see src/Http/Controllers/BatchClearFinishedController.php:14
* @route '/horizon/batches'
*/
destroy.delete = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(options),
    method: 'delete',
})

const clearFinished = {
    destroy: Object.assign(destroy, destroy),
}

export default clearFinished