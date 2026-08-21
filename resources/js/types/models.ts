/**
 * Mirrors the API Resources 1:1 (RULES §2). If you change a Resource, change the
 * matching type here in the same commit — these are the only shapes React knows.
 *
 *   CompanyCardResource -> CompanyCard
 *   CompanyResource     -> Company
 *   ResumeCardResource  -> ResumeCard
 *   ResumeResource      -> Resume
 *   ResumeTemplateCardResource -> ResumeTemplateCard
 *   ResumeTemplateResource     -> ResumeTemplate
 */

/** App\Enums\TemplateLayout — the built-in renderers a template may use. */
export type TemplateLayoutValue =
    | 'classic'
    | 'modern'
    | 'compact'
    | 'professional';

export type LogoPlacementValue = 'hidden' | 'left' | 'centre' | 'right';

export type LogoSizeValue = 'small' | 'medium' | 'large';

export type ResumeStatusValue = 'pending' | 'processing' | 'parsed' | 'failed';

export type ResumeStatusColor = 'neutral' | 'info' | 'success' | 'danger';

export type SectionKey =
    | 'details'
    | 'summary'
    | 'experience'
    | 'education'
    | 'skills'
    | 'certifications'
    | 'languages';

/** ResumeTemplateCardResource -> ResumeTemplateCard */
export type ResumeTemplateCard = {
    id: string;
    slug: string;
    name: string;
    description: string | null;
    layout: TemplateLayoutValue;
    layout_label: string;
    section_order: SectionKey[];
    is_active: boolean;
    companies_count?: number;
    resumes_count?: number;
};

/** ResumeTemplateResource -> ResumeTemplate */
export type ResumeTemplate = ResumeTemplateCard & {
    has_custom_section_order: boolean;
    /** The sample resume the section order was derived from, if any. */
    sample_filename: string | null;
    sample_status: ResumeStatusValue | null;
    sample_status_label: string | null;
    sample_failure_reason: string | null;
    created_at: string | null;
};

/** Generic {value,label} pair from a PHP enum's options(). */
export type EnumOption<T extends string = string> = {
    value: T;
    label: string;
};

export type CompanyCard = {
    id: string;
    slug: string;
    name: string;
    industry: string | null;
    logo_url: string | null;
    brand_color: string;
    /** Slug of the assigned template — what the company form submits. */
    resume_template: string;
    resume_template_name: string;
    logo_placement: LogoPlacementValue;
    logo_size: LogoSizeValue;
    logo_pixels: number;
    is_active: boolean;
    resumes_count?: number;
};

export type Company = CompanyCard & {
    contact_email: string | null;
    contact_phone: string | null;
    website: string | null;
    resume_template_layout: TemplateLayoutValue;
    /** Section order in force, i.e. the assigned template's. */
    section_order: SectionKey[];
    formatting_notes: string | null;
    created_at: string | null;
};

export type ResumeCard = {
    id: string;
    original_filename: string;
    candidate_name: string | null;
    status: ResumeStatusValue;
    status_label: string;
    status_color: ResumeStatusColor;
    page_count: number | null;
    file_size_kb: number;
    failure_reason: string | null;
    uploaded_at: string | null;
    parsed_at: string | null;
    /** Present only on cross-company lists (the dashboard). */
    company?: CompanyCard;
};

/** One rendered block of the ATS document, already ordered by the company's format. */
export type AtsSection =
    | { key: SectionKey; label: string; type: 'text'; text: string }
    | { key: SectionKey; label: string; type: 'tags' | 'list'; items: string[] }
    | {
          key: SectionKey;
          label: string;
          type: 'details';
          rows: { label: string; value: string }[];
      }
    | {
          key: SectionKey;
          label: string;
          type: 'skill_groups';
          items: string[];
          groups: { label: string | null; items: string[] }[];
      }
    | {
          key: SectionKey;
          label: string;
          type: 'timeline';
          entries: AtsTimelineEntry[];
      };

export type AtsTimelineEntry = {
    primary: string;
    secondary: string | null;
    location: string | null;
    period: string;
    highlights: string[];
};

export type AtsLogo = {
    placement: Exclude<LogoPlacementValue, 'hidden'>;
    size: LogoSizeValue;
    pixels: number;
};

export type AtsDocument = {
    header: {
        name: string;
        /** Job title printed under the name, when the source had one. */
        headline: string | null;
        contact_lines: string[];
        centred: boolean;
        /** null when the company has no logo or hid it. */
        logo: AtsLogo | null;
    };
    sections: AtsSection[];
    score: { value: number; band: 'strong' | 'fair' | 'weak'; notes: string[] };
    warnings: string[];
    template: TemplateLayoutValue;
    /** Name of the template this document was produced with. */
    template_name: string;
};

export type Resume = ResumeCard & {
    candidate_email: string | null;
    company: CompanyCard;
    /** The template frozen at upload — name to show, slug to switch with. */
    resume_template: string | null;
    resume_template_slug: string | null;
    ats: AtsDocument | null;
};

/** One day of the dashboard's intake chart. */
export type TrendDay = {
    date: string;
    label: string;
    count: number;
};

/** DashboardSummary::build() — figures only; entity lists come as Resources. */
export type DashboardSummary = {
    totals: {
        companies: number;
        companies_active: number;
        templates: number;
        templates_active: number;
        resumes: number;
        resumes_this_week: number;
        parsed: number;
        failed: number;
        in_flight: number;
    };
    pipeline: {
        status: ResumeStatusValue;
        label: string;
        color: ResumeStatusColor;
        count: number;
        share: number;
    }[];
    trend: TrendDay[];
    ats: {
        average: number | null;
        band: 'strong' | 'fair' | 'weak' | null;
        sampled: number;
        bands: { strong: number; fair: number; weak: number };
    };
    top_companies: { name: string; slug: string; resumes: number }[];
    template_usage: {
        name: string;
        slug: string;
        companies: number;
        resumes: number;
    }[];
};

/** Shape of a Laravel resource collection wrapping a paginator. */
export type Paginated<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        per_page: number;
        to: number | null;
        total: number;
    };
};
