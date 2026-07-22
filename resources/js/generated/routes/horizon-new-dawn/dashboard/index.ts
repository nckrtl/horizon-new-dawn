import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\DashboardController::index
* @see src/Http/Controllers/DashboardController.php:18
* @route '/horizon/dashboard'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/horizon/dashboard',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\DashboardController::index
* @see src/Http/Controllers/DashboardController.php:18
* @route '/horizon/dashboard'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\DashboardController::index
* @see src/Http/Controllers/DashboardController.php:18
* @route '/horizon/dashboard'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\DashboardController::index
* @see src/Http/Controllers/DashboardController.php:18
* @route '/horizon/dashboard'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

const dashboard = {
    index: Object.assign(index, index),
}

export default dashboard