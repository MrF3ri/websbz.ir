export type DateInput = string | Date | null | undefined;

const dateOptions: Intl.DateTimeFormatOptions = {
    calendar: 'persian',
    numberingSystem: 'arabext',
    timeZone: 'Asia/Tehran',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
};

const dateTimeOptions: Intl.DateTimeFormatOptions = {
    ...dateOptions,
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
};

const dateFormatter = new Intl.DateTimeFormat('fa-IR-u-ca-persian', dateOptions);
const dateTimeFormatter = new Intl.DateTimeFormat('fa-IR-u-ca-persian', dateTimeOptions);

function parseDate(value: DateInput): Date | null {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const date = value instanceof Date ? value : new Date(value);

    return Number.isNaN(date.getTime()) ? null : date;
}

export function useJalaliDate() {
    function formatDate(value: DateInput): string {
        const date = parseDate(value);

        return date ? dateFormatter.format(date) : '';
    }

    function formatDateTime(value: DateInput): string {
        const date = parseDate(value);

        return date ? dateTimeFormatter.format(date) : '';
    }

    return {
        formatDate,
        formatDateTime,
    };
}
