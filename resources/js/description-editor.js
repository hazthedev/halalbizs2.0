import { Editor } from '@tiptap/core';
import Document from '@tiptap/extension-document';
import Paragraph from '@tiptap/extension-paragraph';
import Text from '@tiptap/extension-text';
import Bold from '@tiptap/extension-bold';
import Italic from '@tiptap/extension-italic';
import BulletList from '@tiptap/extension-bullet-list';
import OrderedList from '@tiptap/extension-ordered-list';
import ListItem from '@tiptap/extension-list-item';
import HardBreak from '@tiptap/extension-hard-break';
import { UndoRedo } from '@tiptap/extensions/undo-redo';

export default function descriptionEditor(content, field, label) {
    // Keep ProseMirror outside Alpine's reactive proxy (transactions require identity).
    let editor;
    return {
        content,
        active: {},
        init() {
            const updateToolbar = ({ editor }) => {
                this.active = Object.fromEntries(['bold', 'italic', 'bulletList', 'orderedList']
                    .map(name => [name, editor.isActive(name)]));
            };
            editor = new Editor({
                element: this.$refs.editor,
                extensions: [Document, Paragraph, Text, Bold, Italic, BulletList, OrderedList, ListItem, HardBreak, UndoRedo],
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
            editor.chain().focus()[commands[format]]().run();
        },
        destroy() { editor?.destroy(); },
    };
}
