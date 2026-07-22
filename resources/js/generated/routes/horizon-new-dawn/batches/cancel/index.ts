import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\BatchCancelController::store
* @see src/Http/Controllers/BatchCancelController.php:13
* @route '/horizon/batches/{batch}/cancel'
*/
export const store = (args: { batch: string | number } | [batch: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/horizon/batches/{batch}/cancel',
} satisfies RouteDefinition<["post"]>

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\BatchCancelController::store
* @see src/Http/Controllers/BatchCancelController.php:13
* @route '/horizon/batches/{batch}/cancel'
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
* @see \NckRtl\HorizonNewDawn\Http\Controllers\BatchCancelController::store
* @see src/Http/Controllers/BatchCancelController.php:13
* @route '/horizon/batches/{batch}/cancel'
*/
store.post = (args: { batch: string | number } | [batch: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

const cancel = {
    store: Object.assign(store, store),
}

export default cancel