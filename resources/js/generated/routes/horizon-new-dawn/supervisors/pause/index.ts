import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\SupervisorPauseController::store
* @see src/Http/Controllers/SupervisorPauseController.php:18
* @route '/horizon/supervisors/{supervisor}/pause'
*/
export const store = (args: { supervisor: string | number } | [supervisor: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/horizon/supervisors/{supervisor}/pause',
} satisfies RouteDefinition<["post"]>

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\SupervisorPauseController::store
* @see src/Http/Controllers/SupervisorPauseController.php:18
* @route '/horizon/supervisors/{supervisor}/pause'
*/
store.url = (args: { supervisor: string | number } | [supervisor: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { supervisor: args }
    }

    if (Array.isArray(args)) {
        args = {
            supervisor: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        supervisor: args.supervisor,
    }

    return store.definition.url
            .replace('{supervisor}', parsedArgs.supervisor.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\SupervisorPauseController::store
* @see src/Http/Controllers/SupervisorPauseController.php:18
* @route '/horizon/supervisors/{supervisor}/pause'
*/
store.post = (args: { supervisor: string | number } | [supervisor: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\SupervisorPauseController::destroy
* @see src/Http/Controllers/SupervisorPauseController.php:33
* @route '/horizon/supervisors/{supervisor}/pause'
*/
export const destroy = (args: { supervisor: string | number } | [supervisor: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/horizon/supervisors/{supervisor}/pause',
} satisfies RouteDefinition<["delete"]>

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\SupervisorPauseController::destroy
* @see src/Http/Controllers/SupervisorPauseController.php:33
* @route '/horizon/supervisors/{supervisor}/pause'
*/
destroy.url = (args: { supervisor: string | number } | [supervisor: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { supervisor: args }
    }

    if (Array.isArray(args)) {
        args = {
            supervisor: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        supervisor: args.supervisor,
    }

    return destroy.definition.url
            .replace('{supervisor}', parsedArgs.supervisor.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\SupervisorPauseController::destroy
* @see src/Http/Controllers/SupervisorPauseController.php:33
* @route '/horizon/supervisors/{supervisor}/pause'
*/
destroy.delete = (args: { supervisor: string | number } | [supervisor: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const pause = {
    store: Object.assign(store, store),
    destroy: Object.assign(destroy, destroy),
}

export default pause