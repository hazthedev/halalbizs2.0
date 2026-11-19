{{-- HB-SOP guide body, shared by /help/{guide} and /admin/help.
     $content = SopGuide::load(): title, toc (id + title per ## heading), html.
     $stickyTop = the desktop offset under the layout's own sticky bar. --}}
<div class="lg:grid lg:grid-cols-[15rem_minmax(0,65ch)] lg:items-start lg:gap-14">
    <nav aria-labelledby="guide-contents" class="rounded-[var(--radius-card)] border border-line bg-surface p-5 shadow-soft lg:sticky {{ $stickyTop }} lg:max-h-[calc(100vh-8rem)] lg:overflow-y-auto lg:border-0 lg:bg-transparent lg:p-0 lg:pl-1 lg:shadow-none">
        <h2 id="guide-contents" class="font-display text-base font-medium text-ink-head">{{ __('Contents') }}</h2>
        <ol class="mt-3 space-y-0.5 text-sm">
            @foreach ($content['toc'] as $entry)
                <li>
                    <a href="#{{ $entry['id'] }}"
                       class="block py-1.5 text-ink-soft hover:text-emerald focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald lg:border-l lg:border-line lg:pl-3 lg:hover:border-brass">{{ $entry['title'] }}</a>
                </li>
            @endforeach
        </ol>
    </nav>

    {{-- Guide files are rendered with raw HTML stripped (SopGuide::render), so this is safe to print. --}}
    <div class="mt-10 max-w-[65ch] text-base leading-relaxed text-ink lg:mt-0
                [&_h2]:mt-12 [&_h2]:mb-4 [&_h2]:scroll-mt-28 [&_h2]:border-t [&_h2]:border-line [&_h2]:pt-8 [&_h2]:font-display [&_h2]:text-2xl [&_h2]:font-medium [&_h2]:text-ink-head
                [&>h2:first-of-type]:mt-0 [&>h2:first-of-type]:border-t-0 [&>h2:first-of-type]:pt-0
                [&_p]:my-4
                [&_blockquote]:my-6 [&_blockquote]:rounded-[var(--radius-control)] [&_blockquote]:border-l-2 [&_blockquote]:border-warn [&_blockquote]:bg-warn-tint [&_blockquote]:px-4 [&_blockquote]:py-3 [&_blockquote]:text-sm [&_blockquote_p]:my-0
                [&_a]:font-medium [&_a]:text-emerald [&_a]:underline hover:[&_a]:text-emerald-deep
                [&_ul]:my-4 [&_ul]:list-disc [&_ul]:pl-6
                [&_ol]:my-4 [&_ol]:list-decimal [&_ol]:pl-7 [&_ol]:marker:font-display [&_ol]:marker:font-medium [&_ol]:marker:text-brass-deep
                [&_li]:my-2.5 [&_li]:pl-1.5
                [&_strong]:font-semibold [&_strong]:text-ink-head">
        {!! $content['html'] !!}
    </div>
</div>
