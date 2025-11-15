#### Sample Holiday Provider (using [Yasumi](https://yasumi.dev))

Here's an example implementation for HolidayProviderInterface using Yasumi.
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