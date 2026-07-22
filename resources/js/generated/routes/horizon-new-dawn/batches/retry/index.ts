import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\BatchRetryController::store
* @see src/Http/Controllers/BatchRetryController.php:14
* @route '/horizon/batches/{batch}/retry'
*/
export const store = (args: { batch: string | number } | [batch: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/horizon/batches/{batch}/retry',
} satisfies RouteDefinition<["post"]>

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\BatchRetryController::store
* @see src/Http/Controllers/BatchRetryController.php:14
* @route '/horizon/batches/{batch}/retry'
*/
store.url = (args: { batch: string | number } | [batch: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { batch: args }
    }

    if (Array.isArray(args)) {
        args = {
            batch: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        batch: args.batch,
    }

    return store.definition.url
            .replace('{batch}', parsedArgs.batch.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\BatchRetryController::store
* @see src/Http/Controllers/BatchRetryController.php:14
* @route '/horizon/batches/{batch}/retry'
*/
store.post = (args: { batch: string | number } | [batch: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

const retry = {
    store: Object.assign(store, store),
}

export default retry