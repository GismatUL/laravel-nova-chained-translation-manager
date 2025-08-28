<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" @click.self="$emit('close')">
    <div class="w-1/2 bg-white rounded shadow-lg dark:bg-gray-800">
      <slot></slot>
    </div>
  </div>
</template>

<script>
export default { name: "Modal" };
</script>
