import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\QueueClearAllController::destroy
* @see src/Http/Controllers/QueueClearAllController.php:13
* @route '/horizon/queues'
*/
export const destroy = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/horizon/queues',
} satisfies RouteDefinition<["delete"]>

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\QueueClearAllController::destroy
* @see src/Http/Controllers/QueueClearAllController.php:13
* @route '/horizon/queues'
*/
destroy.url = (options?: RouteQueryOptions) => {
    return destroy.definition.url + queryParams(options)
}

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\QueueClearAllController::destroy
* @see src/Http/Controllers/QueueClearAllController.php:13
* @route '/horizon/queues'
*/
destroy.delete = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(options),
    method: 'delete',
})

const clearAll = {
    destroy: Object.assign(destroy, destroy),
}

export default clearAll