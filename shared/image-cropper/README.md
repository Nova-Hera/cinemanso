# image-cropper

Modal de recorte de imagem, sem dependências (JS puro + CSS com variáveis `--ic-*`). A imagem aparece inteira e fixa;
o quadrado de seleção (arrastar = mover, cantos/slider = redimensionar) é o que vira o resultado, um `Blob` quadrado.

Usado no emoji customizado do Neko (`deploy/neko/nekoemoji.js`) e na foto de perfil e no banner do cinemanso
(`resources/js/profile-images.js`, que envia o recorte como upload do Livewire). Com `aspect` (largura/altura, ex.: `4.8` para um banner
1920×400) o quadrado de seleção vira um retângulo com essa proporção; `outputSize` passa a ser a largura do arquivo final e `onSave`
também recebe `width` e `height`. O padrão (`aspect: 1`) continua quadrado.

## Foto de perfil (exemplo)

```js
import ImageCropper from '../../shared/image-cropper/image-cropper.js';
import '../../shared/image-cropper/image-cropper.css';

ImageCropper.open({
  title: 'Foto de perfil',
  outputSize: 256,            // PNG/JPEG final 256×256
  shape: 'circle',            // só a máscara do recorte e das miniaturas; o arquivo é quadrado
  previewSizes: [96, 40],
  mimeType: 'image/jpeg',
  quality: 0.9,
  labels: { save: 'Salvar', cancel: 'Cancelar', replace: 'Trocar imagem', largest: 'Maior', size: 'Tamanho', preview: 'Prévia', close: 'Fechar',
            drop: 'Solte uma imagem aqui, cole, ou', choose: 'escolha um arquivo' },
  async onSave({ blob }) {
    const body = new FormData();
    body.append('photo', blob, 'avatar.jpg');
    const response = await fetch('/profile/photo', { method: 'POST', body, headers: { 'X-CSRF-TOKEN': token } });
    if (!response.ok) throw new Error('Não foi possível salvar');   // a mensagem aparece no modal e ele continua aberto
  },
});
```

Para outro tema, redefina as variáveis em `.ic-root` (`--ic-accent`, `--ic-panel`, `--ic-text`, `--ic-hover`, ...; o cinemanso faz isso em `resources/css/app.css`, claro e escuro). Em páginas sem bundler, carregue
`<script type="module" src="image-cropper.js">` (expõe `window.ImageCropper`) e o CSS.

Opções: `title`, `outputSize`, `shape`, `previewSizes`, `mimeType`/`quality`, `labels`, `note`, `nameField` (campo de nome opcional com
`validate`/`normalize`), `file` (já abre com um arquivo), `stage` (`{ width, height }` da área de edição), `minSelection`, `onSave`, `onClose`.
