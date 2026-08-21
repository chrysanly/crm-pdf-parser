/**
 * Mirrors the API Resources 1:1 (RULES §2). If you change a Resource, change the
 * matching type here in the same commit — these are the only shapes React knows.
 *
 *   CompanyCardResource -> CompanyCard
 *   CompanyResource     -> Company
 *   ResumeCardResource  -> ResumeCard
 *   ResumeResource      -> Resume
 */

export type ResumeTemplateValue =
    'classic' | 'modern' | 'compact' | 'professional';

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

export type TemplateOption = {
    value: ResumeTemplateValue;
    label: string;
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
    resume_template: ResumeTemplateValue;
    resume_template_label: string;
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
    section_order: SectionKey[];
    has_custom_section_order: boolean;
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
    template: ResumeTemplateValue;
};

export type Resume = ResumeCard & {
    candidate_email: string | null;
    company: CompanyCard;
    ats: AtsDocument | null;
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
