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

export default function descriptionEditor(content, field, label) {
    // Keep ProseMirror outside Alpine's reactive proxy (transactions require identity).
    let editor;
    return {
        content,
        active: {},
        async init() {
            const { Editor, extensions } = await loadTiptap();
            const updateToolbar = ({ editor }) => {
                this.active = Object.fromEntries(['bold', 'italic', 'bulletList', 'orderedList']
                    .map(name => [name, editor.isActive(name)]));
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
                        class: 'min-h-40 w-full break-words px-3.5 py-2.5 text-sm text-ink outline-none [&_p]:my-2 [&_ul]:list-disc [&_ul]:ps-6 [&_ol]:list-decimal [&_ol]:ps-6 [&_li_p]:my-0',
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
            const commands = { bold: 'toggleBold', italic: 'toggleItalic', bulletList: 'toggleBulletList', orderedList: 'toggleOrderedList' };
            editor?.chain().focus()[commands[format]]().run();
        },
        destroy() { editor?.destroy(); },
    };
}
