#### Sample Holiday Provider (using [Yasumi](https://yasumi.dev))

This library allows you to plug in a custom holiday provider by implementing
`HolidayProviderInterface`.

This page shows a **sample implementation** using [Yasumi](https://yasumi.dev).

> Dependency note: Yasumi is not required by this package.
> Install it in your project if you want to use this provider.

---

## What the provider receives

The interface is intentionally minimal:

- Input: a `ChronosDate` (date-only, no time component)
- Output: `true` if that calendar day is a holiday, `false` otherwise

Your implementation should ideally be:
- **deterministic** (same date → same answer)
- **side-effect free**
- reasonably **fast**, since it can be called many times during expansion

---

## Example implementation (Yasumi)
```
readonly class DefaultHolidayProvider implements HolidayProviderInterface
{
    public function __construct(
        private string $country, // The country name. @see https://www.yasumi.dev/providers/providers.html
    ) {
    }

    /** @throws ScheduleHumanizerException */
    public function isHoliday(ChronosDate $date): bool
    {
        try {
            $yasumi = Yasumi::create($this->country, (int) $date->format('Y'));
        } catch (RuntimeException | InvalidYearException | UnknownLocaleException | ProviderNotFoundException $e) {
            throw new ScheduleHumanizerException($e->getMessage(), $e->getCode(), $e);
        }

        return $yasumi->isHoliday($date);
    }
}
```