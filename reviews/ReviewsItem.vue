<template>
  <div class="review-row">
    <div class="review-item" :class="{ 'is-admin': item.isManager }" :id="'comment-'+ item.commentId">
      <div class="review-wrapper">
        <div class="review-pic">
          <img :src="item.userLogo"  alt="Пользователь" />
        </div>
        <div class="review-content">
          <div class="review-name-row">
            <span class="review-name" v-if="!item.isManager">{{ item.name }}</span>
            <span class="review-name" v-else>{{ translate('ADMIN') }}</span>
            <span class="review-date">{{ translate('PUBLISHED') }} {{ item.date }}</span>
          </div>
          <div class="review-raiting" v-if="!item.isManager && item.depth <= 0">
            <span class="raiting-label">{{ translate('RANG') }}</span>
            <Raiting :star="item.rang" />
          </div>
          <div class="review-text">
            {{ item.text }}
          </div>
          <div class="row files-row" v-if="item.files && item.files.length > 0">
            <ul class="files-list">
              <li v-for="file in item.files">
                <a :href="file.SRC" :title="file.ORIGINAL_NAME">
                  <AttachIcon />
                  {{ file.ORIGINAL_NAME }}
                </a>
              </li>
            </ul>
          </div>
          <div class="row answer-btn-row" v-if="item.maxDepth <= item.depth || isAuthAdmin">
            <button type="button" class="answer-btn" @click="showAnswerForm">{{ translate('ANSWER') }}</button>
          </div>
          <div class="response-row">
            <ReviewsForm
              :timer="timer"
              :loading="loading"
              :key="item.commentId"
              :fields="fields"
              :item="item"
              v-if="showForm"/>
          </div>
        </div>
      </div>
    </div>
    <div
      class="reviews-list child-list"
      v-if="item.comments"
      :style="{ paddingLeft: ((item.depth + 1) * 16) + 'px' }">
      <reviews-item
        :timer="timer"
        :fields="fields"
        v-for="item in item.comments"
        :item="item"
        :translate="translate"
        :key="item.commentId" />
    </div>
  </div>
</template>

<script>
import Raiting from "./ReviewRaiting";
import ReviewsForm from "./ReviewsForm";
import AttachIcon from './AttachIcon';
export default {
  props: ["item", 'fields', 'params', 'isAuthAdmin', 'timer', 'loading', 'translate'],
  name: "reviews-item",
  data() {
    return {
      showForm: false,
    }
  },
  components: {
    ReviewsForm,
    Raiting,
    AttachIcon
  },
  mounted() {
    this.$root.$on('show-answer-form', (id)=>{
      this.showForm = this.item.commentId === id;
    });
    this.$root.$on('comment-add', (created)=>{
      this.showForm = false;
    });
  },
  methods: {
    showAnswerForm() {
      this.$root.$emit('show-answer-form', this.item.commentId);
    },
  }
};
</script>

<style lang="scss" scoped>
.review-item {
  padding: 30px;
  border-bottom: 1px solid #eaefef;
  transition: background-color 200ms;
  background-color: #fff;
  margin-bottom: 16px;
  &.new-comment, &.selected{
    background-color: #ffe8d8;
  }
  .review-pic {
    width: 40px;
    img {
      max-width: 100%;
    }
  }
  .review-content {
    margin-left: 15px;
    width: calc(100% - 55px);
  }
  &.is-admin {
    background-color: #f7f8f8;
    transition: background-color 200ms;
    &.new-comment, &.selected{
      background-color: #ffe8d8;
    }
    .review-pic {
      width: 80px;
      img {
        max-width: 100%;
      }
    }
    .review-content {
      margin-left: 15px;
      margin-top: 5px;
      width: calc(100% - 95px);
    }
  }
}
.files-list{
  margin: 16px 0 0;
  padding: 0;
  list-style: none;
  li{
    margin-bottom: 4px;
    margin-top: 4px;
    a{
      color: black;
      &:hover{
        color: #e31e24;
      }
    }
  }
}
.answer-btn-row{
  text-align: right;
}
.answer-btn{
  border: none;
  background-color: transparent;
  margin-top: 10px;
  color: #575757;
  cursor: pointer;
  &:hover{
    color: #e31e24;
  }
}
.review-name-row {
  display: flex;
  justify-content: space-between;
}
.review-date {
  margin-left: 10px;
  font-size: 11px;
  color: #7a7c7d;
}
.review-name {
  font-size: 16px;
  font-weight: 500;
}

.review-raiting {
  display: inline-flex;
  align-items: center;
}
.raiting-label {
  font-size: 11px;
  color: #7a7c7d;
  margin-right: 10px;
}
.review-wrapper {
  display: flex;
  flex-wrap: wrap;
}

@media screen and (max-width: 480px) {
  .review-item {
    &.is-admin {
      margin-left: 0px;
      padding: 16px;
      .review-wrapper {
        display: block;
      }
    }
    .review-content {
      width: 100%;
      margin-left: 0;
    }
  }
  .review-text{
    font-size: 14px;
  }
}
</style>