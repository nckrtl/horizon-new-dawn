import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\FailedJobRetryController::store
* @see src/Http/Controllers/FailedJobRetryController.php:14
* @route '/horizon/failed/{job}/retry'
*/
export const store = (args: { job: string | number } | [job: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/horizon/failed/{job}/retry',
} satisfies RouteDefinition<["post"]>

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\FailedJobRetryController::store
* @see src/Http/Controllers/FailedJobRetryController.php:14
* @route '/horizon/failed/{job}/retry'
*/
store.url = (args: { job: string | number } | [job: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { job: args }
    }

    if (Array.isArray(args)) {
        args = {
            job: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        job: args.job,
    }

    return store.definition.url
            .replace('{job}', parsedArgs.job.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\FailedJobRetryController::store
* @see src/Http/Controllers/FailedJobRetryController.php:14
* @route '/horizon/failed/{job}/retry'
*/
store.post = (args: { job: string | number } | [job: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

const retry = {
    store: Object.assign(store, store),
}

export default retry