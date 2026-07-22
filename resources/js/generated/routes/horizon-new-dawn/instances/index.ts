import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
import terminate from './terminate'
import pause from './pause'
/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\RunningInstanceController::index
* @see src/Http/Controllers/RunningInstanceController.php:16
* @route '/horizon/instances'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/horizon/instances',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\RunningInstanceController::index
* @see src/Http/Controllers/RunningInstanceController.php:16
* @route '/horizon/instances'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\RunningInstanceController::index
* @see src/Http/Controllers/RunningInstanceController.php:16
* @route '/horizon/instances'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\RunningInstanceController::index
* @see src/Http/Controllers/RunningInstanceController.php:16
* @route '/horizon/instances'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

const instances = {
    index: Object.assign(index, index),
    terminate: Object.assign(terminate, terminate),
    pause: Object.assign(pause, pause),
}

export default instances