@props([
    'name',
    'value'       => '',
    'placeholder' => '',
    'rows'        => 4,
    'label'       => null,
    'required'    => false,
])

@once
<script>
function reviewEditorData(initialValue) {
    function esc(s) {
        return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // Recursive: processes inner content of each match so formats can stack
    function process(t) {
        const re = /`([^`|]+)(?:\|([^`]+))?`|\*\*\*(.+?)\*\*\*|\*\*(.+?)\*\*|__(.+?)__|~~(.+?)~~|\*(.+?)\*/g;
        let out = '', last = 0, m;
        re.lastIndex = 0;
        while ((m = re.exec(t)) !== null) {
            out += t.slice(last, m.index);
            const [, quote, attr, bolditalic, bold, underline, strike, italic] = m;
            if (quote != null) {
                out += '<span style="display:block;position:relative;margin:0.5rem 0;border:1px solid rgba(161,161,170,0.35);border-left:3px solid rgb(0,123,24);border-radius:0.375rem;background:rgba(161,161,170,0.07);padding:0.7rem 0.9rem 0.6rem 2rem;">'
                     + '<span style="position:absolute;top:0.05rem;left:0.5rem;font-family:Georgia,serif;font-size:1.6rem;line-height:1;color:rgb(0,123,24);opacity:0.55;">&ldquo;</span>'
                     + '<span style="display:block;font-style:italic;">' + quote + '</span>'
                     + (attr ? '<span style="display:block;margin-top:0.4rem;font-size:0.75rem;font-weight:600;letter-spacing:0.05em;text-transform:uppercase;opacity:0.55;">&mdash; ' + attr + '</span>' : '')
                     + '</span>';
            } else if (bolditalic != null) { out += '<strong style="font-weight:700;"><em>' + process(bolditalic) + '</em></strong>';
            } else if (bold      != null) { out += '<strong style="font-weight:700;">' + process(bold) + '</strong>';
            } else if (underline != null) { out += '<u>' + process(underline) + '</u>';
            } else if (strike    != null) { out += '<s style="opacity:0.7;">' + process(strike) + '</s>';
            } else if (italic    != null) { out += '<em>' + process(italic) + '</em>';
            }
            last = re.lastIndex;
        }
        out += t.slice(last);
        return out;
    }

    return {
        content: initialValue,
        focused: false,

        apply(marker, placeholder) {
            const el = this.$refs.textarea;
            const start = el.selectionStart, end = el.selectionEnd;
            const cur = el.value;
            const sel = cur.slice(start, end);
            const MARKERS = ['**', '__', '~~', '*', '`'];  

            const splice = (v, s, e) => {
                el.value = v;
                this.content = v;
                this.$nextTick(() => { el.focus(); el.selectionStart = s; el.selectionEnd = e; });
            };

            const looksDoubled = marker.length === 1 &&
                (sel[1] === marker || sel[sel.length - 2] === marker);
            if (sel.length > 2 * marker.length && sel.startsWith(marker) && sel.endsWith(marker) && !looksDoubled) {
                const inner = sel.slice(marker.length, sel.length - marker.length);
                splice(cur.slice(0, start) + inner + cur.slice(end), start, start + inner.length);
                return;
            }

            let li = start, ri = end;
            while (true) {
                const m = MARKERS.find(m =>
                    li - m.length >= 0 &&
                    cur.slice(li - m.length, li) === m &&
                    cur.slice(ri, ri + m.length) === m);
                if (!m) break;
                if (m === marker) {
                    splice(cur.slice(0, li - m.length) + cur.slice(li, ri) + cur.slice(ri + m.length),
                           start - m.length, end - m.length);
                    return;
                }
                li -= m.length; ri += m.length;
            }

            const text = sel || placeholder;
            splice(cur.slice(0, start) + marker + text + marker + cur.slice(end),
                   start + marker.length, start + marker.length + text.length);
        },

        preview() {
            if (!this.content) return '';
            return process(esc(this.content)).replace(/\n/g, '<br>');
        }
    };
}
</script>
@endonce

<div
    x-data="reviewEditorData({{ Js::from($value) }})"
    style="display:flex; flex-direction:column; gap:0.375rem; width:100%;"
>
    @if ($label)
        <span style="font-size:0.875rem; font-weight:500;" class="text-zinc-700 dark:text-zinc-300">
            {{ $label }}
        </span>
    @endif

    <div
        :style="focused
            ? 'border:1px solid rgb(23,221,98); box-shadow:0 0 0 3px rgba(23,221,98,0.25);'
            : 'border:1px solid rgb(228,228,231);'"
        style="display:flex; flex-direction:column; border-radius:0.5rem; overflow:hidden; transition:border-color 150ms ease, box-shadow 150ms ease;"
        class="dark:border-zinc-700"
    >
        {{-- Toolbar: open/close are the real delimiters; placeholder is inserted default text --}}
        <div
            style="display:flex; gap:0.15rem; padding:0.3rem; border-bottom:1px solid rgb(228,228,231);"
            class="bg-zinc-50 dark:bg-zinc-800 dark:border-zinc-700"
        >
            @php
                $tools = [
                    ['B',  '**', 'texto',        'Negrito',                  'font-weight:700;'],
                    ['I',  '*',  'texto',        'Itálico',                  'font-style:italic;'],
                    ['U',  '__', 'texto',        'Sublinhado',               'text-decoration:underline;'],
                    ['S',  '~~', 'texto',        'Riscado',                  'text-decoration:line-through;'],
                    ['"',  '`',  'texto|Pessoa', 'Citação (com atribuição)', ''],
                ];
            @endphp
            @foreach ($tools as [$lbl, $marker, $placeholder, $ttl, $btnStyle])
                <button
                    type="button"
                    title="{{ $ttl }}"
                    x-on:click="apply({{ Js::from($marker) }}, {{ Js::from($placeholder) }})"
                    style="width:1.7rem; height:1.7rem; border-radius:0.25rem; border:none; background:transparent; cursor:pointer; font-size:0.875rem; {{ $btnStyle }}"
                    class="text-zinc-500 dark:text-zinc-400 hover:bg-zinc-200/60 dark:hover:bg-zinc-600/50 transition-colors"
                >{{ $lbl }}</button>
            @endforeach
        </div>

        {{-- Textarea --}}
        <textarea
            x-ref="textarea"
            x-model="content"
            name="{{ $name }}"
            placeholder="{{ $placeholder }}"
            rows="{{ $rows }}"
            @if ($required) required @endif
            x-on:focus="focused = true"
            x-on:blur="focused = false"
            style="width:100%; box-sizing:border-box; resize:vertical; border:none; outline:none; font-size:0.875rem; padding:0.55rem 0.75rem;"
            class="font-sans text-zinc-900 dark:text-zinc-100 bg-white dark:bg-zinc-900 placeholder-zinc-400 dark:placeholder-zinc-500"
        ></textarea>
    </div>

    {{-- Live preview --}}
    <div x-show="content" style="font-size:0.75rem; line-height:1.5;" class="text-zinc-500 dark:text-zinc-400">
        <span style="font-weight:600; letter-spacing:0.05em; text-transform:uppercase; margin-right:0.4rem;">Prévia</span>
        <span x-html="preview()" class="text-zinc-600 dark:text-zinc-300"></span>
    </div>
</div>
