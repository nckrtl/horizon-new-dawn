import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\HorizonTerminationController::store
* @see src/Http/Controllers/HorizonTerminationController.php:16
* @route '/horizon/instances/terminate'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/horizon/instances/terminate',
} satisfies RouteDefinition<["post"]>

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\HorizonTerminationController::store
* @see src/Http/Controllers/HorizonTerminationController.php:16
* @route '/horizon/instances/terminate'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\HorizonTerminationController::store
* @see src/Http/Controllers/HorizonTerminationController.php:16
* @route '/horizon/instances/terminate'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

const terminate = {
    store: Object.assign(store, store),
}

export default terminate