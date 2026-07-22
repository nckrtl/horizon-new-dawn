import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
import pause from './pause'
/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\SupervisorController::show
* @see src/Http/Controllers/SupervisorController.php:15
* @route '/horizon/supervisors/{supervisor}'
*/
export const show = (args: { supervisor: string | number } | [supervisor: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/horizon/supervisors/{supervisor}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\SupervisorController::show
* @see src/Http/Controllers/SupervisorController.php:15
* @route '/horizon/supervisors/{supervisor}'
*/
show.url = (args: { supervisor: string | number } | [supervisor: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return show.definition.url
            .replace('{supervisor}', parsedArgs.supervisor.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\SupervisorController::show
* @see src/Http/Controllers/SupervisorController.php:15
* @route '/horizon/supervisors/{supervisor}'
*/
show.get = (args: { supervisor: string | number } | [supervisor: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\SupervisorController::show
* @see src/Http/Controllers/SupervisorController.php:15
* @route '/horizon/supervisors/{supervisor}'
*/
show.head = (args: { supervisor: string | number } | [supervisor: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

const supervisors = {
    pause: Object.assign(pause, pause),
    show: Object.assign(show, show),
}

export default supervisors