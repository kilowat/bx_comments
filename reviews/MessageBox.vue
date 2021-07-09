<template>
  <transition name="modal">
    <div class="reviews-message" v-if="show" @click="show = false">
      <div class="modal-mask ">
        <div class="modal-wrapper">
          <div class="modal-container" :class="'type-' + type">
            <button class="btn-close" @click="onClose">
              <span class="close-icon"></span>
            </button>
            <div v-if="header" class="pop-name" :class="{'no-margin' : header && text}">{{ header }}</div>
            <div v-if="text" class="prop-text" v-html="text"></div>
          </div>
        </div>
      </div>
    </div>
  </transition>
</template>

<script>

  export default {
    name: "message-box",
    data() {
      return {
        show: false,
        header: null,
        text: null,
        type: 'default',
      }
    },
    methods: {
      onClose() {
        this.show = false;
        document.querySelector('body').style.overflow = 'auto';
      },
    },
    mounted() {
      this.$root.$on('show-reviews-message', (message)=>{
        this.text = message.text;
        this.type = message.type;
        this.show = true;
        document.querySelector('body').style.overflow = 'hiden';
      })
    }
  };
</script>

<style lang="scss">
  .reviews-message{
    .prop-text{
      margin-bottom: 32px;
      margin-top: 24px;
      font-size: 18px;
    }
    .pop-name{
      font-size: 32px;
      font-weight: bold;
      margin-bottom: 40px;
      &.no-margin{
        margin-bottom: 0;
      }
    }
    .modal-enter {
      opacity: 0;
    }

    .modal-leave-active {
      opacity: 0;
    }

    .modal-enter .modal-container,
    .modal-leave-active .modal-container {
      -webkit-transform: scale(1.1);
      transform: scale(1.1);

    }
    .modal-mask {
      position: fixed;
      z-index: 9998;
      top: 0;
      left: 0;
      width: 100%;
      height: 100vh;
      //height: 100%;
      background-color: rgba(0, 0, 0, 0.5);
      display: table;
      transition: opacity 0.3s ease;
    }

    .modal-wrapper {
      display: table-cell;
      vertical-align: middle;
    }

    .modal-container {
      max-width: 320px;
      width: calc(100% - 32px);
      margin: 0px auto;
      padding: 40px 24px 24px;
      background-color: #fff;
      border-radius: 2px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.33);
      transition: all 0.3s ease;
      margin-top: -15vh;
      position: relative;
      max-height: 100vh;
      overflow-y: auto;
    }
    .btn-close{
      display: block;
      position: absolute;
      height: 24px;
      width: 24px;
      background-color: transparent;
      border:none;
      cursor: pointer;
      right: 40px;
      top: 20px;
      .close-icon{
        position: relative;
        display: block;
        span{
          display: block;
        }
        &:before{
          display: block;
          content: "";
          position: absolute;
          width: 24px;
          height: 2px;
          background-color: #000;
          left: -12px;
          top: 0px;
          transform: rotate(45deg);
        }
        &:after{
          display: block;
          content: "";
          position: absolute;
          width: 24px;
          height: 2px;
          background-color: #000;
          left: -12px;
          top: 0px;
          transform: rotate(-45deg);
        }
      }
    }
    .type-error{
      ul{
        margin:0;
        li{
          color: red;
          margin-bottom: 16px;
        }
      }
    }
    .type-success{
      .prop-text{
        color: green;
      }
    }
  }
  @media screen and (max-width: 480px) {
    .reviews-message{
      .prop-text{
        margin-bottom: 22px;
      }
      .pop-name{
        font-size: 20px;
        margin-bottom: 22px;
        text-align: center;
      }
      .modal-container {
        padding: 16px;
      }
      .btn-close{
        right: 0px;
        top: 8px;
      }
    }
  }
</style>