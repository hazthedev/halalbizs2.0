// Loaded only when a description editor mounts, so buyer pages do not download Tiptap.
const loadTiptap = () => Promise.all([
    import('@tiptap/core'),
    import('@tiptap/extension-document'),
    import('@tiptap/extension-paragraph'),
    import('@tiptap/extension-text'),
    import('@tiptap/extension-bold'),
    import('@tiptap/extension-italic'),
    import('@tiptap/extension-bullet-list'),
    import('@tiptap/extension-ordered-list'),
    import('@tiptap/extension-list-item'),
    import('@tiptap/extension-hard-break'),
    import('@tiptap/extensions/undo-redo'),
]).then(([core, ...mods]) => ({
    Editor: core.Editor,
    extensions: [...mods.slice(0, -1).map(m => m.default), mods.at(-1).UndoRedo],
}));

// Admin pages and help articles also get headings and links (the tags the
// server-side HtmlSanitizer::CMS_TAGS allows). Loaded only for `rich` editors.
const loadRich = () => Promise.all([
    import('@tiptap/extension-heading'),
    import('@tiptap/extension-link'),
]).then(([heading, link]) => [
    heading.default.configure({ levels: [2, 3] }),
    link.default.configure({ openOnClick: false, autolink: true, protocols: ['http', 'https', 'mailto', 'tel'] }),
]);

export default function descriptionEditor(content, field, label, rich = false) {
    // Keep ProseMirror outside Alpine's reactive proxy (transactions require identity).
    let editor;
    return {
        content,
        active: {},
        async init() {
            const [{ Editor, extensions: base }, extra] = await Promise.all([loadTiptap(), rich ? loadRich() : []]);
            const extensions = [...base, ...extra];
            const updateToolbar = ({ editor }) => {
                this.active = Object.fromEntries(['bold', 'italic', 'bulletList', 'orderedList']
                    .map(name => [name, editor.isActive(name)]));
                if (rich) {
                    this.active.h2 = editor.isActive('heading', { level: 2 });
                    this.active.h3 = editor.isActive('heading', { level: 3 });
                    this.active.link = editor.isActive('link');
                }
            };
            editor = new Editor({
                element: this.$refs.editor,
                extensions,
                content: this.content || '',
                editorProps: {
                    attributes: {
                        id: field,
                        role: 'textbox',
                        'aria-label': label,
                        'aria-multiline': 'true',
                        dir: 'auto',
                        class: 'min-h-40 w-full break-words px-3.5 py-2.5 text-sm text-ink outline-none [&_p]:my-2 [&_ul]:list-disc [&_ul]:ps-6 [&_ol]:list-decimal [&_ol]:ps-6 [&_li_p]:my-0 [&_h2]:mt-4 [&_h2]:mb-2 [&_h2]:font-display [&_h2]:text-xl [&_h2]:font-medium [&_h3]:mt-3 [&_h3]:mb-1.5 [&_h3]:text-base [&_h3]:font-semibold [&_a]:text-emerald [&_a]:underline',
                    },
                },
                // No initial serialization: untouched allowed HTML must round-trip byte for byte.
                onUpdate: ({ editor }) => { this.content = editor.isEmpty ? '' : editor.getHTML(); },
                onSelectionUpdate: updateToolbar,
                onTransaction: updateToolbar,
            });
            this.$watch('content', value => {
                if (value !== editor.getHTML() && !(editor.isEmpty && !value)) {
                    editor.commands.setContent(value || '', { emitUpdate: false });
                }
            });
        },
        toggle(format) {
            if (format === 'h2' || format === 'h3') {
                editor?.chain().focus().toggleHeading({ level: format === 'h2' ? 2 : 3 }).run();
                return;
            }
            if (format === 'link') {
                // Empty answer removes the link; Cancel leaves it alone. The server
                // (HtmlSanitizer::isSafeUrl) drops any address that is not http(s), mailto, tel or a site path.
                const href = window.prompt(this.$root.dataset.linkPrompt || 'Link address', editor?.getAttributes('link').href || 'https://');
                if (href === null) return;
                const chain = editor?.chain().focus().extendMarkRange('link');
                (href.trim() === '' ? chain.unsetLink() : chain.setLink({ href: href.trim() })).run();
                return;
            }
            const commands = { bold: 'toggleBold', italic: 'toggleItalic', bulletList: 'toggleBulletList', orderedList: 'toggleOrderedList' };
            editor?.chain().focus()[commands[format]]().run();
        },
        destroy() { editor?.destroy(); },
    };
}
