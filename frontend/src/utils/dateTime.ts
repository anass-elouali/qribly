import dayjs, { type Dayjs } from 'dayjs'
import timezone from 'dayjs/plugin/timezone'
import utc from 'dayjs/plugin/utc'

dayjs.extend(utc)
dayjs.extend(timezone)

export const APP_TIME_ZONE = 'Africa/Casablanca'

const EXPLICIT_TIME_ZONE_PATTERN = /(?:Z|[+-]\d{2}:?\d{2})$/i

export function apiDateTimeToLocalInput(value: string): string {
  return dayjs(value).tz(APP_TIME_ZONE).format('YYYY-MM-DDTHH:mm')
}

export function localInputToApiDateTime(value: string): string {
  return dayjs.tz(value, APP_TIME_ZONE).utc().toISOString()
}

export function parseDateTimeValue(value: string): Dayjs {
  return EXPLICIT_TIME_ZONE_PATTERN.test(value) ? dayjs(value) : parseAppLocalDateTime(value)
}

export function dateTimeValueToApiDateTime(value: string): string {
  return parseDateTimeValue(value).utc().toISOString()
}

export function parseAppLocalDateTime(value: string): Dayjs {
  return dayjs.tz(value, APP_TIME_ZONE)
}

export function inAppTimeZone(value: string): Dayjs {
  return dayjs(value).tz(APP_TIME_ZONE)
}
