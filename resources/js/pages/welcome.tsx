import { Head, Link, usePage } from '@inertiajs/react';
import { dashboard, login } from '@/routes';

type Panel = {
    title: string;
    caption: string;
    lines: string[];
};

const panels: Panel[] = [
    {
        title: 'Start developing',
        caption: 'Boot the full dev stack — server, queue, and Vite.',
        lines: ['composer install && npm install', 'composer dev', '# → http://localhost:8000'],
    },
    {
        title: 'Debugging & tooling',
        caption: 'Local-only observability and static analysis.',
        lines: ['php artisan telescope  # /telescope', 'php artisan pail        # live logs', 'composer analyse        # Larastan'],
    },
    {
        title: 'Feature modules',
        caption: 'Flip an env flag, run one gated command. 🔒',
        lines: ['ENABLE_PDF=true', 'ENABLE_EXCEL=true', 'php artisan features:install'],
    },
    {
        title: 'Authentication',
        caption: 'Login, registration, and social — one command. 🔒',
        lines: ['php artisan auth:setup login-register', 'php artisan auth:setup login-social \\', '  --providers=google'],
    },
];

const stacks = ['Laravel 13', 'React 19', 'Inertia 2', 'TypeScript', 'Tailwind 4', 'Vite'];

export default function Welcome() {
    const { auth } = usePage().props;
    const random = Math.floor(Math.random() * 1000);

    return (
        <>
            <Head title="Boilerplate · Laravel + React" />
            <div className="min-h-screen bg-[#FDFDFC] text-[#1b1b18] dark:bg-[#0a0a0a] dark:text-[#EDEDEC]">
                <div className="mx-auto flex min-h-screen w-full max-w-6xl flex-col p-6 lg:p-10">
                    <header className="flex flex-wrap items-center justify-between gap-4">
                        <div className="flex items-center gap-3">
                            <span className="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-[#f53003] font-semibold text-white dark:bg-[#FF4433]">
                                B
                            </span>
                            <div className="text-sm leading-tight">
                                <p className="font-semibold">Boilerplate</p>
                                <p className="text-[#706f6c] dark:text-[#A1A09A]">Laravel + React</p>
                            </div>
                        </div>
                        <nav className="flex items-center gap-3 text-sm">
                            {auth.user ? (
                                <Link
                                    href={dashboard()}
                                    className="rounded-md border border-[#19140035] px-4 py-1.5 hover:border-[#1915014a] dark:border-[#3E3E3A] dark:hover:border-[#62605b]"
                                >
                                    Dashboard
                                </Link>
                            ) : (
                                <Link
                                    href={login()}
                                    className="rounded-md border border-[#19140035] px-4 py-1.5 hover:border-[#1915014a] dark:border-[#3E3E3A] dark:hover:border-[#62605b]"
                                >
                                    Log in
                                </Link>
                            )}
                        </nav>
                    </header>

                    <main className="grid flex-1 items-center gap-10 py-10 lg:grid-cols-[1.4fr_1fr] lg:py-16">
                        <section>
                            <div className="mb-5 flex flex-wrap gap-2">
                                {stacks.map((stack) => (
                                    <span
                                        key={stack}
                                        className="rounded-full border border-[#19140020] bg-white px-3 py-1 text-xs font-medium text-[#706f6c] dark:border-[#ffffff1a] dark:bg-[#161615] dark:text-[#A1A09A]"
                                    >
                                        {stack}
                                    </span>
                                ))}
                            </div>

                            <h1 className="text-3xl font-semibold tracking-tight sm:text-4xl">A batteries-included Laravel + React starting point.</h1>
                            <p className="mt-3 max-w-xl text-[15px] leading-relaxed text-[#706f6c] dark:text-[#A1A09A]">
                                Env-toggled feature modules, gated install commands, and an AI engineering context — wired and ready. Read{' '}
                                <code className="rounded bg-[#1b1b1810] px-1.5 py-0.5 text-[13px] dark:bg-[#ffffff14]">.claude/context/STARTUP.md</code> first.
                            </p>

                            <div className="mt-8 grid gap-4 sm:grid-cols-2">
                                {panels.map((panel) => (
                                    <article
                                        key={panel.title}
                                        className="rounded-xl border border-[#19140015] bg-white p-4 shadow-sm dark:border-[#ffffff12] dark:bg-[#161615]"
                                    >
                                        <h2 className="text-sm font-semibold">{panel.title}</h2>
                                        <p className="mt-1 text-xs text-[#706f6c] dark:text-[#A1A09A]">{panel.caption}</p>
                                        <pre className="mt-3 overflow-x-auto rounded-lg bg-[#1b1b18] p-3 text-[12px] leading-relaxed text-[#EDEDEC] dark:bg-[#0a0a0a]">
                                            <code>{panel.lines.join('\n')}</code>
                                        </pre>
                                    </article>
                                ))}
                            </div>

                            <p className="mt-5 text-xs text-[#706f6c] dark:text-[#A1A09A]">
                                🔒 <span className="font-medium">gated</span> — <code className="text-[13px]">features:install</code> and{' '}
                                <code className="text-[13px]">auth:setup</code> require maintainer authorization before running.
                            </p>
                        </section>

                        <aside className="relative hidden aspect-[3/4] overflow-hidden rounded-2xl border border-[#19140015] shadow-sm lg:block dark:border-[#ffffff12]">
                            <img
                                src={`https://picsum.photos/720/960?random=${random}`}
                                alt=""
                                className="h-full w-full object-cover"
                                loading="lazy"
                            />
                            <div className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/60 to-transparent p-4">
                                <p className="text-sm font-medium text-white">Ship the fourth site faster than the first.</p>
                            </div>
                        </aside>
                    </main>

                    <footer className="border-t border-[#19140012] pt-4 text-xs text-[#706f6c] dark:border-[#ffffff12] dark:text-[#A1A09A]">
                        Boilerplate · Laravel + React — configure the stack in <code className="text-[13px]">config/features.php</code>.
                    </footer>
                </div>
            </div>
        </>
    );
}
