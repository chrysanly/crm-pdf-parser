/**
 * Mirrors the API Resources 1:1 (RULES §2). If you change a Resource, change the
 * matching type here in the same commit — these are the only shapes React knows.
 *
 *   CompanyCardResource -> CompanyCard
 *   CompanyResource     -> Company
 *   ResumeCardResource  -> ResumeCard
 *   ResumeResource      -> Resume
 */

export type ResumeTemplateValue = 'classic' | 'modern' | 'compact';

export type ResumeStatusValue = 'pending' | 'processing' | 'parsed' | 'failed';

export type ResumeStatusColor = 'neutral' | 'info' | 'success' | 'danger';

export type SectionKey =
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

export type CompanyCard = {
    id: string;
    slug: string;
    name: string;
    industry: string | null;
    logo_url: string | null;
    brand_color: string;
    resume_template: ResumeTemplateValue;
    resume_template_label: string;
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

export type AtsDocument = {
    header: { name: string; contact_lines: string[] };
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
