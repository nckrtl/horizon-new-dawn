import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
import clear from './clear'
import cancel from './cancel'
/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\PendingJobController::destroy
* @see src/Http/Controllers/PendingJobController.php:14
* @route '/horizon/jobs/pending/{job}'
*/
export const destroy = (args: { job: string | number } | [job: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/horizon/jobs/pending/{job}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\PendingJobController::destroy
* @see src/Http/Controllers/PendingJobController.php:14
* @route '/horizon/jobs/pending/{job}'
*/
destroy.url = (args: { job: string | number } | [job: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return destroy.definition.url
            .replace('{job}', parsedArgs.job.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \NckRtl\HorizonNewDawn\Http\Controllers\PendingJobController::destroy
* @see src/Http/Controllers/PendingJobController.php:14
* @route '/horizon/jobs/pending/{job}'
*/
destroy.delete = (args: { job: string | number } | [job: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const pending = {
    clear: Object.assign(clear, clear),
    cancel: Object.assign(cancel, cancel),
    destroy: Object.assign(destroy, destroy),
}

export default pending