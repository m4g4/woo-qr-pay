<?php
/**
 * Minimal shim for chillerlan/php-settings-container when php-qrcode is copied without Composer deps.
 */

namespace chillerlan\Settings;

interface SettingsContainerInterface extends \JsonSerializable {
	public function __get(string $property): mixed;

	public function __set(string $property, mixed $value): void;

	public function __isset(string $property): bool;

	public function __unset(string $property): void;

	public function fromIterable(iterable $settings): static;

	public function toArray(): array;
}

abstract class SettingsContainerAbstract implements SettingsContainerInterface {
	public function __construct(iterable $settings = array()) {
		$this->fromIterable($settings);
	}

	public function __get(string $property): mixed {
		$getter = 'get_' . $property;
		if (method_exists($this, $getter)) {
			return $this->{$getter}();
		}

		return property_exists($this, $property) ? $this->{$property} : null;
	}

	public function __set(string $property, mixed $value): void {
		$setter = 'set_' . $property;
		if (method_exists($this, $setter)) {
			$this->{$setter}($value);
			return;
		}

		if (property_exists($this, $property)) {
			$this->{$property} = $value;
		}
	}

	public function __isset(string $property): bool {
		return property_exists($this, $property);
	}

	public function __unset(string $property): void {
		// Immutable-style container; unset is intentionally ignored.
	}

	public function fromIterable(iterable $settings): static {
		foreach ($settings as $key => $value) {
			$this->__set((string) $key, $value);
		}

		return $this;
	}

	public function toArray(): array {
		return get_object_vars($this);
	}

	public function jsonSerialize(): mixed {
		return $this->toArray();
	}
}
