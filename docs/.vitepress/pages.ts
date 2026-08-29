/**
 * Single source of truth for the documentation order.
 *
 * Filenames keep their NN- prefix so GitHub renders docs/ in reading order;
 * `slug` strips the prefix so site URLs stay stable when pages are reordered.
 */
export type DocPage = {
    file: string
    text: string
    /** Sidebar and "next page" wording: a reason for a reader to keep going. */
    blurb: string
    /** llms.txt wording, when an agent choosing a page needs something more specific than the blurb. */
    agent?: string
}

export type DocSection = {
    text: string
    pages: DocPage[]
}

export const sections: DocSection[] = [
    {
        text: 'Getting started',
        pages: [
            { file: '01-why-this-package', text: 'Why this package?', blurb: 'What the migration converts, and what it deliberately leaves alone.' },
            { file: '02-installation', text: 'Installation', blurb: 'Requirements, and which version to pin against your fluent-validation.' },
            { file: '03-quick-start', text: 'Quick start', blurb: 'One config, three commands, and a diff worth reading.' },
            { file: '04-sets', text: 'Sets', blurb: 'The pipeline in pieces: CONVERT, GROUP, TRAITS, SIMPLIFY, POLISH.' },
        ],
    },
    {
        text: 'Conversion',
        pages: [
            { file: '05-converters', text: 'String and array converters', blurb: 'Pipe strings, rule arrays, Rule:: objects and parent spreads.' },
            { file: '06-livewire', text: 'Livewire attributes', blurb: 'Strip #[Rule] / #[Validate] and generate a rules() method.' },
            { file: '07-grouping', text: 'Wildcard grouping', blurb: 'Fold flat wildcard and dotted keys into each() / children().' },
            { file: '08-traits', text: 'Trait insertion', blurb: 'Add the right fluent-validation trait, including the Filament variant.' },
        ],
    },
    {
        text: 'After the migration',
        pages: [
            { file: '09-simplify', text: 'Simplify', blurb: 'Promote factories, fold chains, and retire the ->rule() escape hatch.' },
            { file: '10-polish', text: 'Docblock polish', blurb: 'Narrow the @return on rules() once the conversion has stabilized.' },
            { file: '11-schema', text: 'Adopting FluentSchema', blurb: 'Trade the repeated FluentRule:: prefix for an injected builder.' },
        ],
    },
    {
        text: 'Control',
        pages: [
            { file: '12-fluent-rules-attribute', text: 'The #[FluentRules] attribute', blurb: 'Opt a method in, and the safety guards the attribute does not lift.' },
            { file: '13-configuration', text: 'Configuration', blurb: 'The four configurable rectors, their wire keys, and the typed DTO builders.' },
        ],
    },
    {
        text: 'Operating it',
        pages: [
            { file: '14-formatter', text: 'Formatter integration', blurb: 'Why the emit is not formatter-clean, and the three fixers that finish it.' },
            { file: '15-diagnostics', text: 'Diagnostics', blurb: 'The skip log, its verbosity tiers, and why the flag is env-only.' },
            { file: '16-parity', text: 'Parity harness', blurb: 'Proving the rewritten rules produce the same error bags at runtime.' },
        ],
    },
    {
        text: 'Reference',
        pages: [
            { file: '17-rules-reference', text: 'Rule reference', blurb: 'Every rector, its set, and what it does — for registering one directly.' },
            { file: '18-limitations', text: 'Detection and limitations', blurb: 'What is detected without config, and what stays untouched.' },
        ],
    },
]

/** Flat reading order — drives rewrites and the sidebar. */
export const pages: DocPage[] = sections.flatMap(section => section.pages)

export const slug = (file: string) => file.replace(/^\d+-/, '')

export const link = (file: string) => `/${slug(file)}`
