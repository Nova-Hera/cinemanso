// Seleção de foto de perfil e banner: usa o recorte compartilhado (shared/image-cropper, o mesmo do emoji do Neko)
// e envia o resultado como upload do Livewire para a propriedade do componente (photo / banner).
// Uso no Blade: x-on:click="pickProfileImage($wire, 'photo')"
import ImageCropper from '../../shared/image-cropper/image-cropper.js';

const LABELS = {
    close: 'Fechar',
    drop: 'Solte uma imagem aqui, cole, ou',
    choose: 'escolha um arquivo',
    preview: 'Prévia',
    size: 'Tamanho',
    largest: 'Maior',
    replace: 'Trocar imagem',
    cancel: 'Cancelar',
    save: 'Usar imagem',
    saving: 'Enviando…',
    notImage: 'Esse arquivo não é uma imagem',
    unreadable: 'Não foi possível ler essa imagem',
    saveFailed: 'Não foi possível enviar a imagem',
    selection: (w, outW, h, outH) => `Seleção ${w} × ${h} px, salva como ${outW} × ${outH}`,
    fileInfo: (w, h, kb) => `${w} × ${h} · ${kb} KB`,
};

const PRESETS = {
    photo: {
        title: 'Foto de perfil',
        outputSize: 256,
        shape: 'circle',
        previewSizes: [96, 40],
        mimeType: 'image/png',
        filename: 'foto.png',
    },
    banner: {
        title: 'Banner',
        outputSize: 1920,
        aspect: 4.8, // 1920×400
        previewSizes: [320],
        mimeType: 'image/jpeg',
        quality: 0.9,
        stage: { width: 480, height: 260 },
        filename: 'banner.jpg',
        note: 'O centro da imagem é o que sempre aparece',
    },
};

window.pickProfileImage = (wire, property) => {
    const { filename, ...preset } = PRESETS[property];

    ImageCropper.open({
        ...preset,
        labels: LABELS,
        onSave: ({ blob }) =>
            new Promise((resolve, reject) => {
                const file = new File([blob], filename, { type: blob.type });

                wire.upload(property, file, resolve, () => reject(new Error(LABELS.saveFailed)));
            }),
    });
};
