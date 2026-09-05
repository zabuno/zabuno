import { t } from '../../../i18n/workspace';
import { Checkbox } from '../../catalog/forms/micro/Checkbox';
import { CHOICE_LABEL_TOUCH_CLASS, Label } from '../../catalog/forms/micro/Label';
import { TextInput } from '../../catalog/forms/micro/TextInput';
import {
    DAY_MINUTES,
    clockToMinute,
    closingMinuteFrom,
    suggestedDraft,
    type OpeningHoursDraft,
} from './openingHours';

export type OpeningHoursFieldsProps = {
    idPrefix: string;
    /** `null` "bu şubenin çalışma saati girilmemiş" demektir — meşru bir hâl. */
    draft: OpeningHoursDraft[] | null;
    onChange: (draft: OpeningHoursDraft[] | null) => void;
    errorText?: string;
};

const DAY_LABEL_KEYS = {
    1: 'workspace.location.hours.day.1',
    2: 'workspace.location.hours.day.2',
    3: 'workspace.location.hours.day.3',
    4: 'workspace.location.hours.day.4',
    5: 'workspace.location.hours.day.5',
    6: 'workspace.location.hours.day.6',
    7: 'workspace.location.hours.day.7',
} as const;

const TIME_INPUT_CLASS = 'min-h-[var(--control-height)]';

/**
 * ŞUBENİN HAFTALIK ÇALIŞMA SAATİ — `docs/109` §6.4.
 *
 * GÜN GÜN sorulur çünkü gerçek bir restoranın haftası tek aralık değildir:
 * pazartesi kapalıdır, cuma gece ikiye kadar açıktır. Kaynağın kartı tek
 * aralık gösteriyor ama o bir ÖZETTİR; tek aralık sorsaydık, haftası
 * değişen her restoran sisteme ya yanlış girer ya hiç girmezdi.
 *
 * "ERTESİ GÜN" KUTUSU YOK. Kapanış açılıştan erkense tek makul okuma
 * ertesi gündür; satır bunu bir soru olarak değil, bir SONUÇ olarak söyler
 * ("next day"). Sahibin cevabını bildiği bir soruyu sormak, formu
 * uzatmaktan başka bir şey yapmaz.
 */
export function OpeningHoursFields({
    idPrefix,
    draft,
    onChange,
    errorText,
}: OpeningHoursFieldsProps) {
    const toggleId = `${idPrefix}-hours-enabled`;
    const helpId = `${idPrefix}-hours-help`;
    const errorId = errorText ? `${idPrefix}-hours-error` : undefined;

    function patchDay(day: number, patch: Partial<OpeningHoursDraft>): void {
        if (draft === null) {
            return;
        }

        onChange(draft.map((row) => (row.day === day ? { ...row, ...patch } : row)));
    }

    return (
        <div className="flex flex-col gap-[var(--space-3)]">
            <div className="flex items-center gap-[var(--space-2)]">
                <Checkbox
                    id={toggleId}
                    checked={draft !== null}
                    aria-describedby={[helpId, errorId].filter(Boolean).join(' ') || undefined}
                    onChange={(event) =>
                        onChange(event.currentTarget.checked ? suggestedDraft() : null)
                    }
                />
                <Label htmlFor={toggleId} className={CHOICE_LABEL_TOUCH_CLASS}>
                    {t('workspace.location.hours.enable')}
                </Label>
            </div>

            {/*
                Yardım ve hata metni JETONLA yazılır, Flowbite'ın
                `HelperText`'iyle değil: o bileşen bugün ham `gray` palet
                basamağı basıyor, yani jeton kökünü atlıyor ve koyu temada
                kendi kararını veriyor. Onu düzeltmek bu paketin işi değil,
                ama bu yüzey onun borcunu devralmak zorunda da değil.
            */}
            <p id={helpId} className="text-body text-fg-secondary">
                {draft === null
                    ? t('workspace.location.hours.empty')
                    : t('workspace.location.hours.help')}
            </p>

            {errorText ? (
                <p
                    id={errorId}
                    role="alert"
                    aria-live="polite"
                    className="text-body text-fg-danger"
                >
                    {errorText}
                </p>
            ) : null}

            {draft === null
                ? null
                : draft.map((row) => {
                      const label = t(DAY_LABEL_KEYS[row.day as keyof typeof DAY_LABEL_KEYS]);
                      const opensMinute = clockToMinute(row.opens);
                      const closesMinute = clockToMinute(row.closes);
                      /*
                          Satır sonucu SÖYLER. Kapanış ertesi güne taştığında
                          "02:00" tek başına belirsizdir; sahip "bu hangi
                          gün" diye sormak zorunda kalmamalı.
                      */
                      const crossesMidnight =
                          !row.closed &&
                          opensMinute !== null &&
                          closesMinute !== null &&
                          closingMinuteFrom(opensMinute, closesMinute) >= DAY_MINUTES;

                      return (
                          <fieldset
                              key={row.day}
                              /*
                                  Her gün kendi GRUBUDUR: "Opens" adında yedi
                                  alan varken, ekran okuyucuyla gezen birine
                                  hangi günü doldurduğunu söyleyen tek şey
                                  budur.

                                  Izgara `auto-fit` ile kurulur: 320 pikselde
                                  gün adı, kapalı kutusu ve iki saat alt alta
                                  iner; geniş ekranda tek satır olur.
                              */
                              className="m-0 grid items-center gap-[var(--space-2)] border-0 p-0"
                              style={{
                                  gridTemplateColumns:
                                      'repeat(auto-fit, minmax(min(100%, 8rem), 1fr))',
                              }}
                          >
                              <legend className="sr-only">{label}</legend>
                              <span aria-hidden="true" className="text-body text-fg">
                                  {label}
                              </span>

                              <span className="flex items-center gap-[var(--space-2)]">
                                  <Checkbox
                                      id={`${idPrefix}-hours-${row.day}-closed`}
                                      checked={row.closed}
                                      onChange={(event) =>
                                          patchDay(row.day, { closed: event.currentTarget.checked })
                                      }
                                  />
                                  <Label
                                      htmlFor={`${idPrefix}-hours-${row.day}-closed`}
                                      className={CHOICE_LABEL_TOUCH_CLASS}
                                  >
                                      {t('workspace.location.hours.closed')}
                                  </Label>
                              </span>

                              <span className="flex flex-col gap-[var(--space-1)]">
                                  <Label htmlFor={`${idPrefix}-hours-${row.day}-opens`}>
                                      {t('workspace.location.hours.opens')}
                                  </Label>
                                  <TextInput
                                      id={`${idPrefix}-hours-${row.day}-opens`}
                                      type="time"
                                      className={TIME_INPUT_CLASS}
                                      disabled={row.closed}
                                      value={row.opens}
                                      onChange={(event) =>
                                          patchDay(row.day, { opens: event.target.value })
                                      }
                                  />
                              </span>

                              <span className="flex flex-col gap-[var(--space-1)]">
                                  <Label htmlFor={`${idPrefix}-hours-${row.day}-closes`}>
                                      {t('workspace.location.hours.closes')}
                                  </Label>
                                  <TextInput
                                      id={`${idPrefix}-hours-${row.day}-closes`}
                                      type="time"
                                      className={TIME_INPUT_CLASS}
                                      disabled={row.closed}
                                      value={row.closes}
                                      onChange={(event) =>
                                          patchDay(row.day, { closes: event.target.value })
                                      }
                                  />
                                  {crossesMidnight ? (
                                      <span className="text-body text-fg-secondary">
                                          {t('workspace.location.hours.nextDay')}
                                      </span>
                                  ) : null}
                              </span>
                          </fieldset>
                      );
                  })}
        </div>
    );
}

export default OpeningHoursFields;
