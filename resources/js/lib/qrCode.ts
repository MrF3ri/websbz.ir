import qrcode from 'qrcode-generator';

export function createQrSvg(value: string): string {
    const content = value.trim();

    if (content === '') {
        throw new Error('A non-empty URL is required to generate a QR code.');
    }

    const code = qrcode(0, 'M');
    code.addData(content, 'Byte');
    code.make();

    return code.createSvgTag({
        cellSize: 8,
        margin: 4,
        scalable: true,
    });
}
