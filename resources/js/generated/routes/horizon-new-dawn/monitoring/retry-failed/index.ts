import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\MonitoringFailedJobRetryController::store
* @see src/Http/Controllers/MonitoringFailedJobRetryController.php:14
* @route '/horizon/monitoring/actions/retry-failed/{tag}'
*/
export const store = (args: { tag: string | number } | [tag: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/horizon/monitoring/actions/retry-failed/{tag}',
} satisfies RouteDefinition<["post"]>

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\MonitoringFailedJobRetryController::store
* @see src/Http/Controllers/MonitoringFailedJobRetryController.php:14
* @route '/horizon/monitoring/actions/retry-failed/{tag}'
*/
store.url = (args: { tag: string | number } | [tag: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { tag: args }
    }

    if (Array.isArray(args)) {
        args = {
            tag: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        tag: args.tag,
    }

    return store.definition.url
            .replace('{tag}', parsedArgs.tag.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\MonitoringFailedJobRetryController::store
* @see src/Http/Controllers/MonitoringFailedJobRetryController.php:14
* @route '/horizon/monitoring/actions/retry-failed/{tag}'
*/
store.post = (args: { tag: string | number } | [tag: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

const retryFailed = {
    store: Object.assign(store, store),
}

export default retryFailed