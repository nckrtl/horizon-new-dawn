import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\FailedJobRetryAllController::store
* @see src/Http/Controllers/FailedJobRetryAllController.php:14
* @route '/horizon/failed/retry-all'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/horizon/failed/retry-all',
} satisfies RouteDefinition<["post"]>

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\FailedJobRetryAllController::store
* @see src/Http/Controllers/FailedJobRetryAllController.php:14
* @route '/horizon/failed/retry-all'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\FailedJobRetryAllController::store
* @see src/Http/Controllers/FailedJobRetryAllController.php:14
* @route '/horizon/failed/retry-all'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

const retryAll = {
    store: Object.assign(store, store),
}

export default retryAll