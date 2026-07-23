import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\DelayedJobReleaseController::store
* @see src/Http/Controllers/DelayedJobReleaseController.php:14
* @route '/horizon/jobs/pending/{job}/release'
*/
export const store = (args: { job: string | number } | [job: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/horizon/jobs/pending/{job}/release',
} satisfies RouteDefinition<["post"]>

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\DelayedJobReleaseController::store
* @see src/Http/Controllers/DelayedJobReleaseController.php:14
* @route '/horizon/jobs/pending/{job}/release'
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
* @see \NckRtl\HorizonNewDawn\Http\Controllers\DelayedJobReleaseController::store
* @see src/Http/Controllers/DelayedJobReleaseController.php:14
* @route '/horizon/jobs/pending/{job}/release'
*/
store.post = (args: { job: string | number } | [job: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

const release = {
    store: Object.assign(store, store),
}

export default release